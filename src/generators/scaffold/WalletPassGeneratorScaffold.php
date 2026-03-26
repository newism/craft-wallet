<?php

namespace newism\wallet\generators\scaffold;

use craft\generator\BaseGenerator;
use Nette\PhpGenerator\PhpNamespace;
use newism\wallet\generators\GeneratorInterface;
use yii\helpers\Inflector;

/**
 * Scaffolds a new wallet pass generator class.
 *
 * Available via `php craft make` when the Wallet Passes plugin is installed.
 * Generates a PHP class implementing GeneratorInterface with all required methods.
 */
class WalletPassGeneratorScaffold extends BaseGenerator
{
    private string $_className;
    private string $_namespace;
    private string $_handle;
    private string $_displayName;
    private string $_applePassType;
    private string $_googleClassSuffix;

    public function run(): bool
    {
        $this->_className = $this->classNamePrompt('Generator class name:', [
            'required' => true,
        ]);

        $this->_namespace = $this->namespacePrompt('Generator namespace:', [
            'default' => "$this->baseNamespace\\generators",
        ]);

        $this->_handle = $this->command->prompt('Generator handle (e.g. "voucher", "event-ticket"):', [
            'required' => true,
            'pattern' => '/^[a-z][a-z0-9\-]*$/',
        ]);

        $this->_displayName = $this->command->prompt('Display name (e.g. "Gift Voucher"):', [
            'required' => true,
            'default' => Inflector::camel2words($this->_className),
        ]);

        $appleTypes = ['storeCard', 'eventTicket', 'coupon', 'boardingPass', 'generic'];
        $typeIndex = $this->command->select('Apple pass type:', $appleTypes);
        $this->_applePassType = $appleTypes[$typeIndex];

        $this->_googleClassSuffix = $this->command->prompt('Google Wallet class suffix:', [
            'required' => true,
            'default' => $this->_handle,
        ]);

        // Build the class
        $namespace = (new PhpNamespace($this->_namespace))
            ->addUse('craft\elements\User')
            ->addUse('newism\wallet\models\Pass')
            ->addUse(GeneratorInterface::class);

        $class = $this->createClass($this->_className, null, [GeneratorInterface::class]);
        $namespace->add($class);

        $class->setComment("{$this->_displayName} wallet pass generator.\n\nRegister this generator in your module's init() method:\n\n"
            . "Event::on(\n"
            . "    \\newism\\wallet\\Wallet::class,\n"
            . "    \\newism\\wallet\\Wallet::EVENT_REGISTER_GENERATORS,\n"
            . "    function(\\newism\\wallet\\events\\RegisterGeneratorsEvent \$event) {\n"
            . "        \$event->generators['{$this->_handle}'] = {$this->_className}::class;\n"
            . "    }\n"
            . ");"
        );

        // handle()
        $class->addMethod('handle')
            ->setStatic()
            ->setReturnType('string')
            ->setBody("return ?;", [$this->_handle]);

        // displayName()
        $class->addMethod('displayName')
            ->setStatic()
            ->setReturnType('string')
            ->setBody("return ?;", [$this->_displayName]);

        // userCanCreatePass()
        $userCanCreateMethod = $class->addMethod('userCanCreatePass')
            ->setReturnType('bool')
            ->setBody(implode("\n", [
                '// TODO: Determine if this user can create a pass.',
                '// Return false if the user already has one, or is not eligible.',
                'return true;',
            ]));
        $userCanCreateMethod->addParameter('user')->setType('craft\\elements\\User');

        // createApplePass()
        $applePassClass = match ($this->_applePassType) {
            'storeCard' => 'StoreCard',
            'eventTicket' => 'EventTicket',
            'coupon' => 'Coupon',
            'boardingPass' => 'BoardingPass',
            'generic' => 'Generic',
        };
        $class->addMethod('createApplePass')
            ->setReturnType('\\Passbook\\Pass')
            ->setBody(implode("\n", [
                '// TODO: Populate with fields, colors, barcode, images',
                "\$applePass = new \\Passbook\\Type\\{$applePassClass}(\$pass->uid);",
                'return $applePass;',
            ]))
            ->addParameter('pass')->setType('newism\\wallet\\models\\Pass');

        // getGooglePassType()
        $class->addMethod('getGooglePassType')
            ->setReturnType('string')
            ->setBody("return 'generic';");

        // getGoogleClassSuffix()
        $class->addMethod('getGoogleClassSuffix')
            ->setReturnType('string')
            ->setBody("return ?;", [$this->_googleClassSuffix]);

        // loadSources()
        $class->addMethod('loadSources')
            ->setReturnType('array')
            ->setBody(implode("\n", [
                '// TODO: Batch-load source objects by ID.',
                '// Return [sourceId => object] map.',
                'return [];',
            ]))
            ->addParameter('sourceIds')->setType('array');

        // createGooglePassObject()
        $googleObjectMethod = $class->addMethod('createGooglePassObject')
            ->setReturnType('array')
            ->setBody(implode("\n", [
                '// TODO: Build the Google Wallet pass object payload.',
                '// Populate with cardTitle, header, barcode, images, etc.',
                '// The service will force-set id, classId, and state.',
                '$user = $pass->getUser();',
                'return [',
                "    'cardTitle' => ['defaultValue' => ['language' => 'en-US', 'value' => self::displayName()]],",
                "    'header' => ['defaultValue' => ['language' => 'en-US', 'value' => \$user->fullName ?: \$user->username]],",
                "    'barcode' => ['type' => 'QR_CODE', 'value' => (string) \$user->id],",
                '];',
            ]));
        $googleObjectMethod->addParameter('pass')->setType('newism\\wallet\\models\\Pass');

        // getUserSettingsContentTemplate()
        $userSettingsMethod = $class->addMethod('getUserSettingsContentTemplate')
            ->setReturnType('array')
            ->setBody(implode("\n", [
                '// TODO: Return [templatePath, variables] for the user settings tab.',
                "return ['wallet/users/_membership-settings', [",
                "    'user' => \$user,",
                "    'eligibleItems' => [],",
                "    'passIndex' => [],",
                "    'generatorHandle' => self::handle(),",
                ']];',
            ]));
        $userSettingsMethod->addParameter('user')->setType('craft\\elements\\User');
        $userSettingsMethod->addParameter('passes')->setType('array');

        // buildGooglePassClassPayload()
        $classMethod = $class->addMethod('buildGooglePassClassPayload')
            ->setReturnType('array')
            ->setBody(implode("\n", [
                '// TODO: Define the Google Wallet card layout for this pass type.',
                '// See: https://developers.google.com/wallet/generic/rest/v1/genericclass',
                "return ['multipleDevicesAndHoldersAllowedStatus' => 'ONE_USER_ALL_DEVICES'];",
            ]));
        $classMethod->addParameter('classId')->setType('string');

        $this->writePhpClass($namespace);

        $this->command->success("**Wallet pass generator created!**");
        $this->command->info("Register it in your module's init() — see the class docblock for the snippet.");
        $this->command->info("Then run: php craft wallet/setup/google-class");

        return true;
    }
}
