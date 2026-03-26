<?php

namespace newism\wallet\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use newism\wallet\Wallet;
use yii\web\Response;

/**
 * Read-only settings dashboard.
 *
 * Displays resolved settings, file status, webhook URLs, and registered generators.
 */
class SettingsController extends Controller
{
    public function beforeAction($action): bool
    {
        $this->requireAdmin();
        return parent::beforeAction($action);
    }

    /**
     * Returns the settings sidebar nav items.
     */
    protected function sidebarItems(): array
    {
        return [
            'config' => [
                'label' => Craft::t('app', 'Config'),
                'url' => 'wallet/settings/config',
            ],
            'generators' => [
                'label' => Craft::t('app', 'Generators'),
                'url' => 'wallet/settings/generators',
            ],
        ];
    }

    /**
     * Config page — resolved settings, file status, webhook URLs.
     * Previously actionIndex().
     */
    public function actionConfig(): Response
    {
        $settings = Wallet::getInstance()->getSettings();

        // Validate both models
        $settings->apple->validate();
        $settings->google->validate();

        $appleHasErrors = $settings->apple->hasErrors();
        $googleHasErrors = $settings->google->hasErrors();

        return $this->asCpScreen()
            ->title('Wallet Settings')
            ->selectedSubnavItem('settings')
            ->addCrumb('Settings', 'settings')
            ->addCrumb('Wallet', 'settings/plugins/wallet')
            ->pageSidebarTemplate('_includes/nav.twig', [
                'selectedItem' => 'config',
                'items' => $this->sidebarItems(),
            ])
            ->tabs([
                'overview' => [
                    'label' => 'Overview',
                    'url' => '#overview',
                ],
                'apple' => [
                    'label' => 'Apple Wallet',
                    'url' => '#apple',
                    'class' => $appleHasErrors ? 'error' : null,
                ],
                'google' => [
                    'label' => 'Google Wallet',
                    'url' => '#google',
                    'class' => $googleHasErrors ? 'error' : null,
                ],
            ])
            ->contentTemplate('wallet/settings/index', [
                'apple' => [
                    'label' => 'Apple Wallet',
                    'errors' => $settings->apple->getErrors(),
                    'config' => [
                        ['passTypeId', 'WALLET_APPLE_PASS_TYPE_ID', $settings->apple->passTypeId],
                        ['teamId', 'WALLET_APPLE_TEAM_ID', $settings->apple->teamId],
                        ['orgName', 'WALLET_APPLE_ORG_NAME', $settings->apple->orgName],
                        ['p12Password', 'WALLET_APPLE_P12_PASSWORD', $settings->apple->p12Password ? '••••••••' : null],
                        ['p12Base64', 'WALLET_APPLE_P12_BASE64', $settings->apple->p12Base64 ? '••••••••' : null],
                        ['p12Path', 'WALLET_APPLE_P12_PATH', $settings->apple->p12Path],
                        ['wwdrCertPath', 'WALLET_APPLE_WWDR_CERT_PATH', $settings->apple->wwdrCertPath],
                    ],
                    'design' => [
                        ['backgroundColor', 'WALLET_APPLE_BACKGROUND_COLOR', $settings->apple->backgroundColor, 'color'],
                        ['foregroundColor', 'WALLET_APPLE_FOREGROUND_COLOR', $settings->apple->foregroundColor, 'color'],
                        ['labelColor', 'WALLET_APPLE_LABEL_COLOR', $settings->apple->labelColor, 'color'],
                        ['memberIdLabel', 'WALLET_APPLE_MEMBER_ID_LABEL', $settings->apple->memberIdLabel],
                        ['nameLabel', 'WALLET_APPLE_NAME_LABEL', $settings->apple->nameLabel],
                    ],
                    'files' => [
                        ['iconPath', 'WALLET_APPLE_ICON_PATH', $settings->apple->iconPath, 'file', UrlHelper::siteUrl('wallet/assets/apple/icon.png')],
                        ['icon2xPath', 'WALLET_APPLE_ICON2X_PATH', $settings->apple->icon2xPath, 'file', UrlHelper::siteUrl('wallet/assets/apple/icon@2x.png')],
                        ['logoPath', 'WALLET_APPLE_LOGO_PATH', $settings->apple->logoPath, 'file', UrlHelper::siteUrl('wallet/assets/apple/logo.png')],
                        ['logo2xPath', 'WALLET_APPLE_LOGO2X_PATH', $settings->apple->logo2xPath, 'file', UrlHelper::siteUrl('wallet/assets/apple/logo@2x.png')],
                        ['stripPath', 'WALLET_APPLE_STRIP_PATH', $settings->apple->stripPath, 'file', UrlHelper::siteUrl('wallet/assets/apple/strip.png')],
                        ['strip2xPath', 'WALLET_APPLE_STRIP2X_PATH', $settings->apple->strip2xPath, 'file', UrlHelper::siteUrl('wallet/assets/apple/strip@2x.png')],
                    ],
                    'urls' => [
                        ['Web Service URL', UrlHelper::siteUrl('wallet/apple')],
                        ['Add to Wallet', UrlHelper::siteUrl('wallet/passes/add-to-wallet')],
                    ],
                ],
                'google' => [
                    'label' => 'Google Wallet',
                    'errors' => $settings->google->getErrors(),
                    'config' => [
                        ['issuerId', 'WALLET_GOOGLE_ISSUER_ID', $settings->google->issuerId],
                        ['orgName', 'WALLET_GOOGLE_ORG_NAME', $settings->google->orgName],
                        ['classSuffix', 'WALLET_GOOGLE_CLASS_SUFFIX', $settings->google->classSuffix],
                        ['serviceAccountJsonBase64', 'WALLET_GOOGLE_SERVICE_ACCOUNT_JSON_BASE64', $settings->google->serviceAccountJsonBase64 ? '••••••••' : null],
                        ['serviceAccountJsonPath', 'WALLET_GOOGLE_SERVICE_ACCOUNT_PATH', $settings->google->serviceAccountJsonPath],
                    ],
                    'design' => [
                        ['backgroundColor', 'WALLET_GOOGLE_BACKGROUND_COLOR', $settings->google->backgroundColor, 'color'],
                        ['subHeader', 'WALLET_GOOGLE_SUB_HEADER', $settings->google->subHeader],
                        ['memberIdLabel', 'WALLET_GOOGLE_MEMBER_ID_LABEL', $settings->google->memberIdLabel],
                    ],
                    'files' => [
                        ['logoPath', 'WALLET_GOOGLE_LOGO_PATH', $settings->google->logoPath, 'file', UrlHelper::siteUrl('wallet/assets/google/logo.png')],
                        ['heroPath', 'WALLET_GOOGLE_HERO_PATH', $settings->google->heroPath, 'file', UrlHelper::siteUrl('wallet/assets/google/hero.png')],
                    ],
                    'urls' => [
                        ['Webhook Callback', UrlHelper::siteUrl('wallet/google/webhook')],
                        ['Add to Wallet', UrlHelper::siteUrl('wallet/passes/add-to-wallet')],
                    ],
                ],
            ]);
    }

    /**
     * Generators page — lists all registered pass generators.
     */
    public function actionGenerators(): Response
    {
        $generators = Wallet::getInstance()->getGeneratorService()->getGenerators();

        return $this->asCpScreen()
            ->title('Wallet Settings')
            ->selectedSubnavItem('settings')
            ->addCrumb('Settings', 'settings')
            ->addCrumb('Wallet', 'settings/plugins/wallet')
            ->pageSidebarTemplate('_includes/nav.twig', [
                'selectedItem' => 'generators',
                'items' => $this->sidebarItems(),
            ])
            ->contentTemplate('wallet/settings/generators', [
                'generators' => $generators,
            ]);
    }
}
