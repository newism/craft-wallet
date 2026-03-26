<?php

namespace newism\wallet\generators;

use Craft;
use craft\elements\User;
use craft\helpers\UrlHelper;
use newism\wallet\models\Pass;
use newism\wallet\query\PassQuery;
use newism\wallet\services\ApplePassService;
use newism\wallet\Wallet;
use Passbook\Pass\Barcode;
use Passbook\Pass\Field;
use Passbook\Pass\Image;
use Passbook\Pass\Structure;
use Passbook\Type\StoreCard;

/**
 * Built-in membership card generator.
 *
 * Single pass per user, keyed on the user's ID.
 */
class MembershipGenerator implements GeneratorInterface
{
    public static function handle(): string
    {
        return 'membership';
    }

    public static function displayName(): string
    {
        return 'Membership Cards';
    }

    public function userCanCreatePass(User $user): bool
    {
        $passQuery = new PassQuery();
        $passQuery->generatorHandle(self::handle());
        $passQuery->userId($user->id);
        $passQuery->limit(1);

        return !$passQuery->exists();
    }

    public function loadSources(array $sourceIds): array
    {
        // Membership source is the User (loaded via pass->userId, not sourceId)
        // Return empty — user is already available via $pass->getUser()
        return [];
    }

    public function getUserSettingsContentTemplate(User $user, array $passes): array
    {
        // Eligible items — currently just the user, future: groups
        $eligibleItems = [
            [
                'label' => $user->fullName ?: $user->username,
                'sourceId' => null,
                'element' => $user,
            ],
        ];

        // Index passes by sourceId for lookup (null sourceId for membership)
        $passIndex = [];
        foreach ($passes as $pass) {
            $passIndex[$pass->sourceId ?? 'null'] = $pass;
        }

        return ['wallet/users/_membership-settings', [
            'user' => $user,
            'eligibleItems' => $eligibleItems,
            'passIndex' => $passIndex,
            'generatorHandle' => self::handle(),
        ]];
    }

    public function createApplePass(Pass $pass): \Passbook\Pass
    {
        $user = $pass->getUser();
        $appleSettings = Wallet::getInstance()->getSettings()->apple;

        $storeCard = new StoreCard($pass->uid, 'Membership Pass');
        $storeCard->setSharingProhibited(true);
        $storeCard->setBackgroundColor(ApplePassService::hexToRgb($appleSettings->backgroundColor));
        $storeCard->setForegroundColor(ApplePassService::hexToRgb($appleSettings->foregroundColor));
        $storeCard->setLabelColor(ApplePassService::hexToRgb($appleSettings->labelColor));

        $barcode = new Barcode(Barcode::TYPE_QR, (string)$user->id);
        $barcode->setAltText((string)$user->id);
        $storeCard->addBarcode($barcode);

        // Images
        $images = [
            [$appleSettings->iconPath, 'icon'],
            [$appleSettings->icon2xPath, 'icon@2x'],
            [$appleSettings->logoPath, 'logo'],
            [$appleSettings->logo2xPath, 'logo@2x'],
            [$appleSettings->stripPath, 'strip'],
            [$appleSettings->strip2xPath, 'strip@2x'],
        ];
        foreach ($images as [$aliasPath, $type]) {
            $path = Craft::getAlias($aliasPath);
            if (file_exists($path)) {
                $storeCard->addImage(new Image($path, $type));
            }
        }

        // Fields
        $structure = new Structure();

        $memberIdField = new Field('memberId', (string)$user->id);
        $memberIdField->setLabel($appleSettings->memberIdLabel);
        $structure->addHeaderField($memberIdField);

        $nameField = new Field('name', $user->fullName ?: $user->username);
        $nameField->setLabel($appleSettings->nameLabel);
        $structure->addSecondaryField($nameField);

        $storeCard->setStructure($structure);

        return $storeCard;
    }

    public function createGooglePassObject(Pass $pass): array
    {
        $user = $pass->getUser();
        $google = Wallet::getInstance()->getSettings()->google;
        $orgName = $google->orgName ?: 'Membership';

        $objectPayload = [
            'cardTitle' => [
                'defaultValue' => [
                    'language' => 'en-US',
                    'value' => $orgName,
                ],
            ],
            'header' => [
                'defaultValue' => [
                    'language' => 'en-US',
                    'value' => $user->fullName ?: $user->username,
                ],
            ],
            'subHeader' => [
                'defaultValue' => [
                    'language' => 'en-US',
                    'value' => $google->subHeader,
                ],
            ],
            'hexBackgroundColor' => $google->backgroundColor,
            'textModulesData' => [
                [
                    'header' => $google->memberIdLabel,
                    'body' => (string)$user->id,
                    'id' => 'memberId',
                ],
            ],
            'barcode' => [
                'type' => 'QR_CODE',
                'value' => (string)$user->id,
                'alternateText' => (string)$user->id,
            ],
            'linksModuleData' => [
                'uris' => [
                    [
                        'uri' => UrlHelper::siteUrl(),
                        'description' => 'Member Portal',
                        'id' => 'memberPortal',
                    ],
                ],
            ],
        ];

        $logoPath = Craft::getAlias($google->logoPath);
        if (file_exists($logoPath)) {
            $objectPayload['logo'] = ['sourceUri' => ['uri' => UrlHelper::siteUrl('wallet/assets/google/logo.png')]];
        }

        $heroPath = Craft::getAlias($google->heroPath);
        if (file_exists($heroPath)) {
            $objectPayload['heroImage'] = ['sourceUri' => ['uri' => UrlHelper::siteUrl('wallet/assets/google/hero.png')]];
        }

        return $objectPayload;
    }

    public function getGooglePassType(): string
    {
        return 'generic';
    }

    public function getGoogleClassSuffix(): string
    {
        return Wallet::getInstance()->getSettings()->google->classSuffix;
    }

    public function buildGooglePassClassPayload(string $classId): array
    {
        return [
            'multipleDevicesAndHoldersAllowedStatus' => 'ONE_USER_ALL_DEVICES',
            'classTemplateInfo' => [
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
            ],
        ];
    }
}
