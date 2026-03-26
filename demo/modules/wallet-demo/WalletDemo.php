<?php

namespace modules\walletdemo;

use Craft;
use newism\wallet\events\BuildApplePassEvent;
use newism\wallet\events\BuildGooglePassClassEvent;
use newism\wallet\events\BuildGooglePassObjectEvent;
use newism\wallet\services\ApplePassService;
use newism\wallet\services\GooglePassService;
use Passbook\Pass\Barcode;
use Passbook\Pass\Field;
use Passbook\Pass\Image;
use Passbook\Pass\Structure;
use yii\base\Event;
use yii\base\Module;

/**
 * Wallet Demo Module
 *
 * Demonstrates how to customise Apple and Google Wallet passes
 * using the wallet plugin's events. Applies a black and gold theme.
 */
class WalletDemo extends Module
{
    public function init(): void
    {
        Craft::setAlias('@modules/walletdemo', __DIR__);

        parent::init();

        $this->attachEventHandlers();
    }

    private function attachEventHandlers(): void
    {
        // Customise Apple Wallet pass
        Event::on(
            ApplePassService::class,
            ApplePassService::EVENT_BUILD_APPLE_PASS,
            function (BuildApplePassEvent $event) {
                $storeCard = $event->storeCard;
                $passData = $event->passData;

                // Black and gold theme
                $storeCard->setBackgroundColor('rgb(0, 0, 0)');
                $storeCard->setForegroundColor('rgb(212, 175, 55)');
                $storeCard->setLabelColor('rgb(169, 140, 44)');

                // Gold QR barcode
                $barcode = new Barcode(Barcode::TYPE_QR, (string)$passData['id']);
                $barcode->setAltText((string)$passData['id']);
                $storeCard->addBarcode($barcode);

                // Structure
                $structure = new Structure();

                $memberIdField = new Field('memberId', (string)$passData['id']);
                $memberIdField->setLabel('Member ID');
                $structure->addHeaderField($memberIdField);

                $nameField = new Field('name', $passData['fullName']);
                $nameField->setLabel('Name');
                $structure->addSecondaryField($nameField);

                $storeCard->setStructure($structure);

                // Images from config/wallet/apple/
                $configPath = Craft::getAlias('@root/config/wallet/apple/ul');
                $images = [
                    ['icon.png', 'icon'], ['icon@2x.png', 'icon@2x'],
                    ['logo.png', 'logo'], ['logo@2x.png', 'logo@2x'],
                    ['strip.png', 'strip'], ['strip@2x.png', 'strip@2x'],
                ];
                foreach ($images as [$file, $type]) {
                    if (file_exists($configPath . '/' . $file)) {
                        $storeCard->addImage(new Image($configPath . '/' . $file, $type));
                    }
                }
            }
        );

        // Customise Google Wallet pass object
        Event::on(
            GooglePassService::class,
            GooglePassService::EVENT_BUILD_GOOGLE_PASS_OBJECT,
            function (BuildGooglePassObjectEvent $event) {
                $passData = $event->passData;

                // Black and gold theme
                $event->payload['backgroundColor'] = '#000000';
                $event->payload['cardTitle'] = [
                    'defaultValue' => [
                        'language' => 'en-US',
                        'value' => 'Gold Membership',
                    ],
                ];
                $event->payload['header'] = [
                    'defaultValue' => [
                        'language' => 'en-US',
                        'value' => $passData['fullName'],
                    ],
                ];
                $event->payload['subHeader'] = [
                    'defaultValue' => [
                        'language' => 'en-US',
                        'value' => 'Gold Member',
                    ],
                ];
            }
        );

        // Customise Google Wallet pass class template
        Event::on(
            GooglePassService::class,
            GooglePassService::EVENT_BUILD_GOOGLE_PASS_CLASS,
            function (BuildGooglePassClassEvent $event) {
                $event->payload['classTemplateInfo'] = [
                    'cardTemplateOverride' => [
                        'cardRowTemplateInfos' => [
                            [
                                'oneItem' => [
                                    'item' => [
                                        'firstValue' => [
                                            'fields' => [
                                                ['fieldPath' => "object.textModulesData['memberId']"],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }
        );
    }
}
