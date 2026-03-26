<?php

namespace newism\wallet;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\controllers\UsersController;
use craft\elements\User;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineEditUserScreensEvent;
use craft\events\DefineGqlTypeFieldsEvent;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\generator\Command;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeManager;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\helpers\Typecast;
use craft\helpers\UrlHelper;
use craft\log\MonologTarget;
use craft\services\Fields;
use craft\services\Gql;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use GraphQL\Type\Definition\Type;
use Monolog\Formatter\LineFormatter;
use newism\wallet\behaviors\UserWalletBehavior;
use newism\wallet\db\WalletTable;
use newism\wallet\fields\WalletPasses;
use newism\wallet\generators\scaffold\WalletPassGeneratorScaffold;
use newism\wallet\gql\types\WalletPassGqlType;
use newism\wallet\jobs\UpdateUserPassesJob;
use newism\wallet\models\AppleSettings;
use newism\wallet\models\GoogleSettings;
use newism\wallet\models\Settings;
use newism\wallet\services\ApplePassService;
use newism\wallet\services\GeneratorService;
use newism\wallet\services\GooglePassService;
use newism\wallet\services\GooglePassService as GooglePassServiceAlias;
use newism\wallet\services\PassService;
use newism\wallet\web\twig\WalletVariable;
use Psr\Log\LogLevel;
use Throwable;
use yii\base\Event;
use yii\db\Query;

/**
 * Wallet plugin
 *
 * @method static Wallet getInstance()
 * @property-read GeneratorService $generatorService
 * @property-read PassService $passService
 * @property-read ApplePassService $applePass
 * @property-read ApplePassService $applePassService
 * @property-read GooglePassServiceAlias $googlePassService
 * @property-read mixed $settingsResponse
 * @property-read null|array $cpNavItem
 * @property-read Settings $settings
 * @property-read GooglePassService $googlePass
 */
class Wallet extends Plugin
{
    /**
     * Log category for general wallet plugin logging.
     */
    public const LOG = 'wallet';

    /**
     * Log category for Apple Wallet webhooks.
     */
    public const LOG_APPLE_WEBHOOK = 'wallet-apple-webhook';

    /**
     * Log category for Google Wallet webhooks.
     */
    public const LOG_GOOGLE_WEBHOOK = 'wallet-google-webhook';

    // Permissions
    public const PERMISSION_VIEW_PASSES = 'wallet:viewPasses';
    public const PERMISSION_CREATE_PASSES = 'wallet:createPasses';
    public const PERMISSION_DELETE_PASSES = 'wallet:deletePasses';
    public const PERMISSION_VIEW_OTHER_USERS_PASSES = 'wallet:viewOtherUsersPasses';
    public const PERMISSION_CREATE_OTHER_USERS_PASSES = 'wallet:createOtherUsersPasses';
    public const PERMISSION_DELETE_OTHER_USERS_PASSES = 'wallet:deleteOtherUsersPasses';

    /**
     * @event RegisterGeneratorsEvent Fired when registering pass generators.
     */
    public const EVENT_REGISTER_GENERATORS = 'registerGenerators';

    public const SCREEN_WALLET_PASSES = 'wallet-passes';

    public string $schemaVersion = '1.1.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public function getCpNavItem(): ?array
    {
        $navItem = parent::getCpNavItem();
        $navItem['label'] = Craft::t('app', 'Wallet Passes');
        $navItem['icon'] = 'wallet';

        $user = Craft::$app->getUser()->getIdentity();
        if (!$user || !$user->can(self::PERMISSION_VIEW_OTHER_USERS_PASSES)) {
            return null;
        }

        $subnav = [
            'passes' => [
                'label' => Craft::t('app', 'Passes'),
                'url' => 'wallet',
            ],
            'settings' => [
                'label' => Craft::t('app', 'Settings'),
                'url' => 'wallet/settings/config',
            ],
        ];

        $subnav['settings']['subnav'] = [
            'config' => [
                'label' => Craft::t('app', 'Config'),
                'url' => 'wallet/settings/config',
            ],
            'generators' => [
                'label' => Craft::t('app', 'Generators'),
                'url' => 'wallet/settings/generators',
            ],
        ];

        $navItem['subnav'] = $subnav;

        return $navItem;
    }

    public static function config(): array
    {
        return [
            'components' => [
                'generatorService' => GeneratorService::class,
                'passService' => PassService::class,
                'applePassService' => ApplePassService::class,
                'googlePassService' => GooglePassService::class,
            ],
        ];
    }

    public function getGeneratorService(): GeneratorService
    {
        return $this->get('generatorService');
    }

    public function getPassService(): PassService
    {
        return $this->get('passService');
    }

    public function getApplePassService(): ApplePassService
    {
        return $this->get('applePassService');
    }

    public function getGooglePassService(): GooglePassService
    {
        return $this->get('googlePassService');
    }

    public function init(): void
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'newism\\wallet\\console\\controllers';
        }

        parent::init();

        // Register custom log targets
        $this->_registerLogTargets();

        $this->_attachEventHandlers();

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            $this->_registerTemplateRoots();
        });
    }

    /**
     * @inheritdoc
     */
    protected function afterInstall(): void
    {
        parent::afterInstall();

        // Copy config/wallet/ directory (wallet.php, certs, images, READMEs)
        $source = dirname(__DIR__) . '/config/wallet';
        $destination = Craft::getAlias('@root/config/wallet');

        if (is_dir($source) && !is_dir($destination)) {
            FileHelper::copyDirectory($source, $destination);
            Craft::info('Copied wallet config to config/wallet/', self::LOG);
        }
    }

    /**
     * Returns the plugin settings.
     *
     * Merge order: model defaults → config/wallet.php → environment variables.
     * Env vars always win. Uses App::envConfig() with prefixes:
     * - Apple Wallet: WALLET_APPLE_ (e.g. WALLET_APPLE_PASS_TYPE_ID)
     * - Google Wallet: WALLET_GOOGLE_ (e.g. WALLET_GOOGLE_ISSUER_ID)
     */
    public function getSettings(): Settings
    {
        if (isset($this->_settings)) {
            return $this->_settings;
        }

        $configPath = Craft::getAlias('@root/config/wallet/wallet.php');
        $config = file_exists($configPath) ? require $configPath : [];
        $settings = new Settings(is_array($config) ? $config : []);

        // Apply env var overrides — env vars always win
        $this->_applyEnvConfig($settings->apple, AppleSettings::class, 'WALLET_APPLE_');
        $this->_applyEnvConfig($settings->google, GoogleSettings::class, 'WALLET_GOOGLE_');

        return $this->_settings = $settings;
    }

    private ?Settings $_settings = null;

    /**
     * Applies environment variable overrides to a BaseConfig model.
     */
    private function _applyEnvConfig(object $model, string $class, string $prefix): void
    {
        $envConfig = App::envConfig($class, $prefix);
        Typecast::properties($class, $envConfig);

        foreach ($envConfig as $name => $value) {
            if (method_exists($model, $name)) {
                try {
                    $model->$name($value);
                    continue;
                } catch (Throwable) {
                }
            }
            $model->$name = $value;
        }
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(
            UrlHelper::cpUrl('wallet/settings/config')
        );
    }

    /**
     * Registers custom log targets for the wallet plugin.
     */
    private function _registerLogTargets(): void
    {
        $formatter = new LineFormatter(
            format: "%datetime% [%level_name%] %message%\n",
            dateFormat: 'Y-m-d H:i:s',
        );

        // General wallet plugin log
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => self::LOG,
            'categories' => [self::LOG],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
            'maxFiles' => 14,
            'formatter' => $formatter,
        ]);

        // Apple Wallet webhook log
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => self::LOG_APPLE_WEBHOOK,
            'categories' => [self::LOG_APPLE_WEBHOOK],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
            'maxFiles' => 14,
            'formatter' => $formatter,
        ]);

        // Google Wallet webhook log
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => self::LOG_GOOGLE_WEBHOOK,
            'categories' => [self::LOG_GOOGLE_WEBHOOK],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
            'maxFiles' => 14,
            'formatter' => $formatter,
        ]);
    }

    private function _attachEventHandlers(): void
    {
        // Register the `craft make` generator scaffold
        if (class_exists(Command::class)) {
            Event::on(
                Command::class,
                Command::EVENT_REGISTER_GENERATORS,
                function(RegisterComponentTypesEvent $event) {
                    $event->types[] = WalletPassGeneratorScaffold::class;
                }
            );
        }

        // Register craft.wallet Twig variable
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                $event->sender->set('wallet', WalletVariable::class);
            }
        );

        // Attach WalletBehavior to User elements
        Event::on(
            User::class,
            User::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                $event->behaviors['wallet'] = UserWalletBehavior::class;
            }
        );

        // Register URL rules for site requests
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // Add to wallet (token-based public URL)
                $event->rules['wallet/passes/add-to-wallet'] = 'wallet/passes/add-to-wallet';
                // Serve images for wallet passes (Apple and Google)
                $event->rules['wallet/assets/<platform:apple|google>/<filename>.png'] = 'wallet/passes/serve-image';

                // Apple Wallet Web Service endpoints (using dedicated webhook controller)
                // Register/Unregister device for pass updates
                $event->rules['wallet/apple/v1/devices/<deviceId>/registrations/<passTypeId>/<serialNumber>'] = 'wallet/apple-webhook/device-registration';
                // List updatable passes for a device
                $event->rules['wallet/apple/v1/devices/<deviceId>/registrations/<passTypeId>'] = 'wallet/apple-webhook/list-updatable-passes';
                // Get updated pass
                $event->rules['wallet/apple/v1/passes/<passTypeId>/<serialNumber>'] = 'wallet/apple-webhook/get-pass';
                // Log endpoint
                $event->rules['wallet/apple/v1/log'] = 'wallet/apple-webhook/log';

                // Google Wallet
                // Google Wallet - Webhook callback
                $event->rules['wallet/google/webhook'] = 'wallet/google-webhook/callback';

                // Dev mode only: test kitchen sink
                if (Craft::$app->getConfig()->getGeneral()->devMode) {
                    $event->rules['wallet/test'] = ['template' => 'wallet/test'];
                }
            }
        );

        // Register URL rules for CP requests
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // Wallet Passes screen routes
                $event->rules['myaccount/' . self::SCREEN_WALLET_PASSES] = 'wallet/user-settings';
                $event->rules['users/<userId:\d+>/' . self::SCREEN_WALLET_PASSES] = 'wallet/user-settings';

                // CP Wallet Passes section
                $event->rules['wallet'] = 'wallet/wallet/index';
                $event->rules['wallet/passes'] = 'wallet/passes/index';
                $event->rules['wallet/passes/view'] = 'wallet/passes/view';

                // Settings routes
                $event->rules['wallet/settings'] = 'wallet/settings/config';
                $event->rules['wallet/settings/config'] = 'wallet/settings/config';
                $event->rules['wallet/settings/generators'] = 'wallet/settings/generators';
            }
        );

        // Register Wallet Passes screen in user edit navigation
        Event::on(
            UsersController::class,
            UsersController::EVENT_DEFINE_EDIT_SCREENS,
            function(DefineEditUserScreensEvent $event) {
                $currentUser = Craft::$app->getUser()->getIdentity();
                if (!$currentUser) {
                    return;
                }

                // Show the Wallet Passes tab if the user can view their own or others' passes
                $canView = $currentUser->can(self::PERMISSION_VIEW_PASSES)
                    || $currentUser->can(self::PERMISSION_VIEW_OTHER_USERS_PASSES);

                if ($canView) {
                    $event->screens[self::SCREEN_WALLET_PASSES] = [
                        'label' => Craft::t('wallet', 'Wallet Passes'),
                    ];
                }
            }
        );

        // Listen for user saves to update wallet passes
        Event::on(
            User::class,
            Element::EVENT_AFTER_SAVE,
            function(ModelEvent $event) {
                /** @var User $user */
                $user = $event->sender;

                // Skip if this is a new user (no passes yet) or propagating
                if ($event->isNew || $user->propagating) {
                    return;
                }

                // Check if user has any passes
                $hasPass = (new Query())
                    ->from(WalletTable::PASSES)
                    ->where(['userId' => $user->id])
                    ->exists();

                if ($hasPass) {
                    Craft::$app->getQueue()->push(new UpdateUserPassesJob([
                        'userId' => $user->id,
                    ]));
                }
            }
        );

        // Register user permissions
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('wallet', 'Wallet Passes'),
                    'permissions' => [
                        self::PERMISSION_VIEW_PASSES => [
                            'label' => Craft::t('wallet', 'View their own passes'),
                        ],
                        self::PERMISSION_CREATE_PASSES => [
                            'label' => Craft::t('wallet', 'Create their own passes'),
                        ],
                        self::PERMISSION_DELETE_PASSES => [
                            'label' => Craft::t('wallet', 'Delete their own passes'),
                        ],
                        self::PERMISSION_VIEW_OTHER_USERS_PASSES => [
                            'label' => Craft::t('wallet', "View other users' passes"),
                            'nested' => [
                                self::PERMISSION_CREATE_OTHER_USERS_PASSES => [
                                    'label' => Craft::t('wallet', "Create passes for other users"),
                                ],
                                self::PERMISSION_DELETE_OTHER_USERS_PASSES => [
                                    'label' => Craft::t('wallet', "Delete other users' passes"),
                                ],
                            ],
                        ],
                    ],
                ];
            }
        );

        // Register wallet GQL schema permission under Users
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS,
            static function(RegisterGqlSchemaComponentsEvent $event) {
                $usersLabel = Craft::t('app', 'Users');
                $event->queries[$usersLabel]['wallet.passes:read'] = [
                    'label' => Craft::t('wallet', 'Query for wallet passes'),
                ];
            }
        );

        // Add walletPasses field to User GraphQL type
        Event::on(
            TypeManager::class,
            TypeManager::EVENT_DEFINE_GQL_TYPE_FIELDS,
            function(DefineGqlTypeFieldsEvent $event) {
                if ($event->typeName !== 'User') {
                    return;
                }

                // Only expose wallet passes if the schema permits it
                $allowedEntities = \craft\helpers\Gql::extractAllowedEntitiesFromSchema();
                if (!isset($allowedEntities['wallet'])) {
                    return;
                }

                // Create the WalletPass type via registry to avoid duplicate registration
                $passTypeName = 'WalletPass';
                $passType = GqlEntityRegistry::getOrCreate($passTypeName, fn() => new WalletPassGqlType([
                    'name' => $passTypeName,
                    'fields' => [
                        'id' => Type::nonNull(Type::int()),
                        'uid' => Type::nonNull(Type::string()),
                        'generatorHandle' => Type::nonNull(Type::string()),
                        'sourceId' => Type::int(),
                        'sourceIndex' => Type::int(),
                        'dateCreated' => Type::nonNull(Type::string()),
                        'dateUpdated' => Type::nonNull(Type::string()),
                        'lastUpdatedAt' => Type::string(),
                    ],
                ]));

                $event->fields['walletPasses'] = [
                    'name' => 'walletPasses',
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($passType))),
                    'resolve' => function($source) {
                        /** @var UserWalletBehavior $behavior */
                        $behavior = $source->getBehavior('wallet');
                        return $behavior?->getPasses() ?? [];
                    },
                ];

                $event->fields['hasWalletPasses'] = [
                    'name' => 'hasWalletPasses',
                    'type' => Type::nonNull(Type::boolean()),
                    'resolve' => function($source) {
                        /** @var UserWalletBehavior $behavior */
                        $behavior = $source->getBehavior('wallet');
                        return $behavior?->hasPasses() ?? false;
                    },
                ];
            }
        );
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = WalletPasses::class;
        });
    }

    private function _registerTemplateRoots(): void
    {
        // Register CP template roots
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $e) {
                if (is_dir($baseDir = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates')) {
                    $e->roots[$this->id] = $baseDir;
                }
            }
        );

        // Register site template roots
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $e) {
                if (is_dir($baseDir = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates')) {
                    $e->roots[$this->id] = $baseDir;
                }
            }
        );
    }
}
