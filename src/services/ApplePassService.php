<?php

namespace newism\wallet\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use DateTime;
use Exception;
use GuzzleHttp\Exception\ClientException;
use newism\wallet\db\WalletTable;
use newism\wallet\events\BuildApplePassEvent;
use newism\wallet\models\AppleDevice;
use newism\wallet\models\Pass;
use newism\wallet\Wallet;
use Passbook\PassFactory;
use RuntimeException;
use SplFileInfo;

/**
 * Apple Pass Service
 *
 * Handles creation and generation of Apple Wallet passes.
 */
class ApplePassService extends Component
{
    /**
     * @event BuildApplePassEvent Triggered when building an Apple Wallet pass.
     */
    public const EVENT_BUILD_APPLE_PASS = 'buildApplePass';

    private ?string $_tempP12Path = null;

    // =========================================================================
    // P12 Certificate
    // =========================================================================

    private function _getP12FilePath(): string
    {
        $base64 = Wallet::getInstance()->getSettings()->apple->p12Base64;
        if ($base64) {
            if ($this->_tempP12Path === null || !file_exists($this->_tempP12Path)) {
                $this->_tempP12Path = Craft::$app->getPath()->getTempPath() . '/wallet/apple/certificate.p12';
                $dir = dirname($this->_tempP12Path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                file_put_contents($this->_tempP12Path, base64_decode($base64));
            }
            return $this->_tempP12Path;
        }

        $settings = Wallet::getInstance()->getSettings()->apple;
        $path = Craft::getAlias($settings->p12Path);
        if (!file_exists($path)) {
            throw new RuntimeException("Apple P12 certificate not found: {$settings->p12Path}. Set WALLET_APPLE_P12_BASE64 env var for serverless environments.");
        }

        return $path;
    }


    /**
     * Voids an Apple pass and pushes the update to devices.
     * The pass will appear grayed out in Apple Wallet.
     */
    public function voidPass(Pass $pass): void
    {
        $generator = $pass->getGenerator();
        if (!$generator) {
            return;
        }

        // Generate the pass with voided flag
        $applePass = $generator->createApplePass($pass);

        $apple = Wallet::getInstance()->getSettings()->apple;

        $applePass->setPassTypeIdentifier($apple->passTypeId);
        $applePass->setWebServiceURL(UrlHelper::siteUrl('wallet/apple'));
        $applePass->setAuthenticationToken($pass->authToken);
        $applePass->setVoided(true);

        // Update the stored JSON so the webhook serves the voided version
        $passJson = json_encode($applePass->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->updatePassJson($pass, $passJson);

        // Push to devices so they fetch the voided pass
        $this->sendPushNotifications($pass);
    }

    /**
     * Updates the stored Apple pass JSON and last updated timestamp.
     * Only updates lastUpdatedAt when the pass content actually changed.
     * Returns true if the pass content changed.
     */
    public function updatePassJson(Pass $pass, string $json): bool
    {
        $changed = $pass->applePassJson === null || hash('sha256', $pass->applePassJson) !== hash('sha256', $json);

        $data = ['applePassJson' => $json];
        if ($changed) {
            $data['lastUpdatedAt'] = Db::prepareDateForDb(new DateTime());
        }

        Db::update(WalletTable::PASSES, $data, ['id' => $pass->id]);

        return $changed;
    }

    // =========================================================================
    // Device CRUD
    // =========================================================================

    public function getDevicesForPass(Pass $pass): array
    {
        $rows = (new Query())
            ->from(['d' => WalletTable::APPLE_DEVICES])
            ->innerJoin(['dp' => WalletTable::APPLE_DEVICE_PASSES], '[[dp.deviceId]] = [[d.id]]')
            ->where(['dp.passId' => $pass->id])
            ->all();

        return array_map(fn($row) => AppleDevice::fromRow($row), $rows);
    }

    public function registerDevice(Pass $pass, string $deviceLibraryIdentifier, ?string $pushToken): bool
    {
        // Find or create the device
        $device = (new Query())
            ->from(WalletTable::APPLE_DEVICES)
            ->where(['deviceLibraryIdentifier' => $deviceLibraryIdentifier])
            ->one();

        $isNew = false;

        if (!$device) {
            $now = Db::prepareDateForDb(new DateTime());
            Db::insert(WalletTable::APPLE_DEVICES, [
                'deviceLibraryIdentifier' => $deviceLibraryIdentifier,
                'pushToken' => $pushToken ?? '',
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => StringHelper::UUID(),
            ]);
            $deviceId = (int)Craft::$app->getDb()->getLastInsertID();
            $isNew = true;
        } else {
            $deviceId = (int)$device['id'];
            if ($pushToken) {
                Db::update(WalletTable::APPLE_DEVICES, [
                    'pushToken' => $pushToken,
                ], ['id' => $deviceId]);
            }
        }

        // Check if the relationship already exists
        $exists = (new Query())
            ->from(WalletTable::APPLE_DEVICE_PASSES)
            ->where(['deviceId' => $deviceId, 'passId' => $pass->id])
            ->exists();

        if (!$exists) {
            Db::insert(WalletTable::APPLE_DEVICE_PASSES, [
                'deviceId' => $deviceId,
                'passId' => $pass->id,
            ]);
            return true;
        }

        return $isNew;
    }

    public function unregisterDevice(Pass $pass, string $deviceLibraryIdentifier): bool
    {
        $device = (new Query())
            ->from(WalletTable::APPLE_DEVICES)
            ->where(['deviceLibraryIdentifier' => $deviceLibraryIdentifier])
            ->one();

        if (!$device) {
            return false;
        }

        $deviceId = (int)$device['id'];

        // Remove the relationship
        Db::delete(WalletTable::APPLE_DEVICE_PASSES, [
            'deviceId' => $deviceId,
            'passId' => $pass->id,
        ]);

        // If no other passes, delete the device
        $hasOtherPasses = (new Query())
            ->from(WalletTable::APPLE_DEVICE_PASSES)
            ->where(['deviceId' => $deviceId])
            ->exists();

        if (!$hasOtherPasses) {
            Db::delete(WalletTable::APPLE_DEVICES, ['id' => $deviceId]);
        }

        return true;
    }

    public function deleteDeviceById(int $deviceId): bool
    {
        $exists = (new Query())
            ->from(WalletTable::APPLE_DEVICES)
            ->where(['id' => $deviceId])
            ->exists();

        if (!$exists) {
            return false;
        }

        Db::delete(WalletTable::APPLE_DEVICE_PASSES, ['deviceId' => $deviceId]);
        Db::delete(WalletTable::APPLE_DEVICES, ['id' => $deviceId]);

        return true;
    }

    // =========================================================================
    // Push Notifications
    // =========================================================================

    public function sendPushNotifications(Pass $pass): int
    {
        $pushTokens = (new Query())
            ->select(['d.pushToken'])
            ->from(['d' => WalletTable::APPLE_DEVICES])
            ->innerJoin(['dp' => WalletTable::APPLE_DEVICE_PASSES], '[[dp.deviceId]] = [[d.id]]')
            ->where(['dp.passId' => $pass->id])
            ->andWhere(['not', ['d.pushToken' => '']])
            ->column();

        if (empty($pushTokens)) {
            return 0;
        }

        $passTypeIdentifier = Wallet::getInstance()->getSettings()->apple->passTypeId;

        $sent = 0;
        foreach ($pushTokens as $pushToken) {
            if ($this->_sendApnsPush($pushToken, $passTypeIdentifier)) {
                $sent++;
            }
        }

        Craft::info("Sent {$sent} push notifications for pass {$pass->id}", Wallet::LOG);
        return $sent;
    }

    private function _sendApnsPush(string $pushToken, string $passTypeIdentifier): bool
    {
        $p12File = $this->_getP12FilePath();
        $p12Password = Wallet::getInstance()->getSettings()->apple->p12Password;

        try {
            $client = Craft::createGuzzleClient([
                'base_uri' => 'https://api.push.apple.com',
                'cert' => [$p12File, $p12Password],
                'version' => 2.0,
            ]);

            $client->post("/3/device/{$pushToken}", [
                'headers' => ['apns-topic' => $passTypeIdentifier],
                'json' => json_decode('{}'),
            ]);

            Craft::info("APNs push sent successfully to token: " . substr($pushToken, 0, 8) . "...", Wallet::LOG);
            return true;
        } catch (ClientException $e) {
            $httpCode = $e->getResponse()->getStatusCode();
            $responseBody = (string)$e->getResponse()->getBody();

            if ($httpCode === 400) {
                $responseJson = json_decode($responseBody, true);
                if (isset($responseJson['reason']) && $responseJson['reason'] === 'BadDeviceToken') {
                    Craft::warning("APNs BadDeviceToken, removing device: " . substr($pushToken, 0, 8) . "...", Wallet::LOG);
                    Db::delete(WalletTable::APPLE_DEVICES, ['pushToken' => $pushToken]);
                    return false;
                }
            }

            Craft::warning("APNs push failed: HTTP {$httpCode}, response: {$responseBody}", Wallet::LOG);
            return false;
        } catch (Exception $e) {
            Craft::warning("APNs push failed: " . $e->getMessage(), Wallet::LOG);
            return false;
        }
    }

    // =========================================================================
    // Pass Generation
    // =========================================================================

    /**
     * Rebuilds Apple pass JSON and checks for changes.
     * Used by UpdateUserPassesJob for change detection without packaging.
     * Returns true if the pass content changed.
     */
    public function updateApplePassJson(Pass $pass): bool
    {
        $generator = $pass->getGenerator();
        if (!$generator) {
            throw new RuntimeException("No generator found for handle: {$pass->generatorHandle}");
        }

        $applePass = $generator->createApplePass($pass);

        $apple = Wallet::getInstance()->getSettings()->apple;

        if ($this->hasEventHandlers(self::EVENT_BUILD_APPLE_PASS)) {
            $event = new BuildApplePassEvent([
                'applePass' => $applePass,
                'pass' => $pass,
                'user' => $pass->getUser(),
            ]);
            $this->trigger(self::EVENT_BUILD_APPLE_PASS, $event);
            $applePass = $event->applePass;
        }

        $applePass->setPassTypeIdentifier($apple->passTypeId);
        $applePass->setWebServiceURL(UrlHelper::siteUrl('wallet/apple'));
        $applePass->setAuthenticationToken($pass->authToken);

        $passJson = json_encode($applePass->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $this->updatePassJson($pass, $passJson);
    }

    public function generatePkpass(Pass $pass): SplFileInfo
    {
        $generator = $pass->getGenerator();
        if (!$generator) {
            throw new RuntimeException("No generator found for handle: {$pass->generatorHandle}");
        }

        // Generator creates, populates, and configures the Apple pass
        $applePass = $generator->createApplePass($pass);

        $apple = Wallet::getInstance()->getSettings()->apple;
        $passTypeIdentifier = $apple->passTypeId;


        // Event hook for additional customisation
        if ($this->hasEventHandlers(self::EVENT_BUILD_APPLE_PASS)) {
            $event = new BuildApplePassEvent([
                'applePass' => $applePass,
                'pass' => $pass,
                'user' => $pass->getUser(),
            ]);
            $this->trigger(self::EVENT_BUILD_APPLE_PASS, $event);
            $applePass = $event->applePass;
        }

        // Force-set infrastructure fields (cannot be overridden)
        $applePass->setSerialNumber($pass->uid);
        $applePass->setPassTypeIdentifier($passTypeIdentifier);
        $applePass->setWebServiceURL(UrlHelper::siteUrl('wallet/apple'));
        $applePass->setAuthenticationToken($pass->authToken);

        // Capture pass JSON for change detection and debugging
        $passJson = json_encode($applePass->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->updatePassJson($pass, $passJson);

        // Sign and package
        $outputPath = Craft::$app->getPath()->getTempPath() . '/wallet/apple';
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0777, true);
        }

        $factory = new PassFactory(
            $passTypeIdentifier,
            $apple->teamId,
            $apple->orgName,
            $this->_getP12FilePath(),
            $apple->p12Password,
            Craft::getAlias($apple->wwdrCertPath),
        );
        $factory->setOutputPath($outputPath);
        $factory->setOverwrite(true);

        return $factory->package($applePass);
    }

    public static function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgb($r, $g, $b)";
    }

    // =========================================================================
    // Webhook Helpers
    // =========================================================================

    public function getUpdatablePasses(string $deviceLibraryIdentifier, ?string $passesUpdatedSince): array
    {
        $deviceId = (new Query())
            ->select(['id'])
            ->from(WalletTable::APPLE_DEVICES)
            ->where(['deviceLibraryIdentifier' => $deviceLibraryIdentifier])
            ->scalar();

        if (!$deviceId) {
            return ['serialNumbers' => [], 'lastUpdated' => 0];
        }

        $query = (new Query())
            ->select(['p.uid', 'p.lastUpdatedAt'])
            ->from(['p' => WalletTable::PASSES])
            ->innerJoin(['dp' => WalletTable::APPLE_DEVICE_PASSES], '[[dp.passId]] = [[p.id]]')
            ->where(['dp.deviceId' => $deviceId]);

        if ($passesUpdatedSince) {
            $since = (new DateTime())->setTimestamp((int)$passesUpdatedSince);
            $query->andWhere(['>', 'p.lastUpdatedAt', $since->format('Y-m-d H:i:s')]);
        }

        $rows = $query->all();

        if (empty($rows)) {
            return ['serialNumbers' => [], 'lastUpdated' => 0];
        }

        $serialNumbers = [];
        $maxUpdated = 0;

        foreach ($rows as $row) {
            $serialNumbers[] = $row['uid'];
            $timestamp = strtotime($row['lastUpdatedAt']);
            if ($timestamp > $maxUpdated) {
                $maxUpdated = $timestamp;
            }
        }

        return [
            'serialNumbers' => $serialNumbers,
            'lastUpdated' => $maxUpdated,
        ];
    }
}
