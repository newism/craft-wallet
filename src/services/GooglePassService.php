<?php

namespace newism\wallet\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use Firebase\JWT\JWT;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\Middleware\AuthTokenMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\HandlerStack;
use newism\wallet\db\WalletTable;
use newism\wallet\events\BuildGooglePassClassEvent;
use newism\wallet\events\BuildGooglePassObjectEvent;
use newism\wallet\generators\GeneratorInterface;
use newism\wallet\models\Pass;
use newism\wallet\Wallet;
use RuntimeException;

/**
 * Google Pass Service
 *
 * Handles creation and management of Google Wallet passes using the Generic Pass type.
 *
 * @see https://developers.google.com/wallet/generic
 */
class GooglePassService extends Component
{
    /**
     * @event BuildGooglePassObjectEvent Triggered when building a Google Wallet pass object.
     */
    public const EVENT_BUILD_GOOGLE_PASS_OBJECT = 'buildGooglePassObject';

    /**
     * @event BuildGooglePassClassEvent Triggered when building the Google Wallet pass class.
     */
    public const EVENT_BUILD_GOOGLE_PASS_CLASS = 'buildGooglePassClass';

    private ?ClientInterface $_client = null;
    private ?array $_serviceAccount = null;

    // =========================================================================
    // Auth / Config
    // =========================================================================

    private function _getClient(): ClientInterface
    {
        if ($this->_client === null) {
            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/wallet_object.issuer',
                $this->_getServiceAccount()
            );

            $middleware = new AuthTokenMiddleware($credentials);
            $stack = HandlerStack::create();
            $stack->push($middleware);

            $this->_client = new Client([
                'handler' => $stack,
                'auth' => 'google_auth',
            ]);
        }

        return $this->_client;
    }

    private function _getServiceAccount(): array
    {
        if ($this->_serviceAccount === null) {
            $google = Wallet::getInstance()->getSettings()->google;
            $jsonString = $google->serviceAccountJsonBase64;

            if ($jsonString) {
                $this->_serviceAccount = json_decode(base64_decode($jsonString), true);
            } else {
                $credentialsFile = Craft::getAlias($google->serviceAccountJsonPath);

                if (!file_exists($credentialsFile)) {
                    throw new RuntimeException("Google service account credentials file not found: {$google->serviceAccountJsonPath}. Set WALLET_GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 env var for serverless environments.");
                }

                $this->_serviceAccount = json_decode(file_get_contents($credentialsFile), true);
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Invalid JSON in Google service account credentials');
            }
        }

        return $this->_serviceAccount;
    }

    public function getIssuerId(): string
    {
        return Wallet::getInstance()->getSettings()->google->issuerId;
    }

    private function _getApiBaseUrl(): string
    {
        return 'https://walletobjects.googleapis.com';
    }

    /**
     * Updates the stored Google pass JSON.
     * Google doesn't use lastUpdatedAt (no modification-based webhook protocol).
     * Returns true if the pass content changed.
     */
    public function updatePassJson(Pass $pass, string $json): bool
    {
        $changed = $pass->googlePassJson === null || hash('sha256', $pass->googlePassJson) !== hash('sha256', $json);

        Db::update(WalletTable::PASSES, [
            'googlePassJson' => $json,
        ], ['id' => $pass->id]);

        return $changed;
    }

    // =========================================================================
    // Pass Class Management
    // =========================================================================

    /**
     * Creates or updates a Google Wallet pass class from a generator-provided payload.
     *
     * Used by the `wallet/setup/google-class` command to register classes for
     * each generator. The EVENT_BUILD_GOOGLE_PASS_CLASS event still fires for
     * additional customisation.
     *
     * @param string $classId The full class ID (issuerId.classSuffix)
     * @param array $classPayload The class template from the generator
     * @return array The API response
     */
    public function createOrUpdatePassClassFromPayload(string $classId, array $classPayload, string $googlePassType = 'generic', ?GeneratorInterface $generator = null): array
    {
        $callbackUrl = UrlHelper::siteUrl('wallet/google/webhook');

        // Fire the event for additional customisation
        if ($this->hasEventHandlers(self::EVENT_BUILD_GOOGLE_PASS_CLASS)) {
            $eventParams = [
                'payload' => $classPayload,
                'classId' => $classId,
            ];
            if ($generator) {
                $eventParams['generator'] = $generator;
            }
            $event = new BuildGooglePassClassEvent($eventParams);
            $this->trigger(self::EVENT_BUILD_GOOGLE_PASS_CLASS, $event);
            $classPayload = $event->payload;
        }

        // Force-set id and callback (cannot be overridden)
        $classPayload['id'] = $classId;
        $classPayload['callbackOptions'] = ['url' => $callbackUrl];

        $client = $this->_getClient();
        $url = $this->_getApiBaseUrl() . "/walletobjects/v1/{$googlePassType}Class/";

        try {
            $response = $client->put($url . $classId, ['json' => $classPayload]);
            Craft::info("Updated Google Wallet class: {$classId}", Wallet::LOG);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                $response = $client->post($url, ['json' => $classPayload]);
                Craft::info("Created Google Wallet class: {$classId}", Wallet::LOG);
            } else {
                throw new RuntimeException("Failed to create/update Google Wallet class: {$e->getMessage()}");
            }
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    // =========================================================================
    // Pass Object Management
    // =========================================================================

    public function createOrUpdatePassObject(Pass $pass): array
    {
        $generator = $pass->getGenerator();
        if (!$generator) {
            throw new RuntimeException("No generator found for handle: {$pass->generatorHandle}");
        }

        $objectId = $this->getIssuerId() . '.' . $pass->uid;
        $classId = $this->getIssuerId() . '.' . $generator->getGoogleClassSuffix();

        // Generator creates and populates the object payload
        $objectPayload = $generator->createGooglePassObject($pass);

        // Event hook for additional customisation
        if ($this->hasEventHandlers(self::EVENT_BUILD_GOOGLE_PASS_OBJECT)) {
            $event = new BuildGooglePassObjectEvent([
                'payload' => $objectPayload,
                'pass' => $pass,
                'user' => $pass->getUser(),
            ]);
            $this->trigger(self::EVENT_BUILD_GOOGLE_PASS_OBJECT, $event);
            $objectPayload = $event->payload;
        }

        // Force-set infrastructure fields (cannot be overridden)
        $objectPayload['id'] = $objectId;
        $objectPayload['classId'] = $classId;
        $objectPayload['state'] = 'ACTIVE';

        // Use the generator's Google pass type for the correct API endpoint
        // e.g. 'generic' → genericObject, 'eventTicket' → eventTicketObject
        $googlePassType = $generator->getGooglePassType();
        $client = $this->_getClient();
        $url = $this->_getApiBaseUrl() . "/walletobjects/v1/{$googlePassType}Object/";

        try {
            $response = $client->put($url . $objectId, ['json' => $objectPayload]);
            Craft::info("Updated Google Wallet object: {$objectId}", Wallet::LOG);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                $response = $client->post($url, ['json' => $objectPayload]);
                Craft::info("Created Google Wallet object: {$objectId}", Wallet::LOG);
            } else {
                throw new RuntimeException("Failed to create/update Google Wallet object: {$e->getMessage()}");
            }
        }

        // Capture pass JSON for change detection and debugging
        $passJson = json_encode($objectPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->updatePassJson($pass, $passJson);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function deletePassObject(Pass $pass): bool
    {
        $generator = $pass->getGenerator();
        $googlePassType = $generator ? $generator->getGooglePassType() : 'generic';
        $objectId = $this->getIssuerId() . '.' . $pass->uid;
        $client = $this->_getClient();
        $url = $this->_getApiBaseUrl() . "/walletobjects/v1/{$googlePassType}Object/" . $objectId;

        try {
            $client->delete($url);
            Craft::info("Deleted Google Wallet object: {$objectId}", Wallet::LOG);
            return true;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return true;
            }
            throw new RuntimeException("Failed to delete Google Wallet object: {$e->getMessage()}");
        }
    }

    // =========================================================================
    // Add to Wallet URL Generation
    // =========================================================================

    public function generateAddToWalletUrl(Pass $pass): string
    {
        $serviceAccount = $this->_getServiceAccount();
        $objectId = $this->getIssuerId() . '.' . $pass->uid;

        $this->createOrUpdatePassObject($pass);

        // The JWT payload key depends on the Google pass type:
        // 'generic' → 'genericObjects', 'eventTicket' → 'eventTicketObjects'
        $generator = $pass->getGenerator();
        $googlePassType = $generator ? $generator->getGooglePassType() : 'generic';
        $payloadKey = "{$googlePassType}Objects";

        $claims = [
            'iss' => $serviceAccount['client_email'],
            'aud' => 'google',
            'iat' => time(),
            'exp' => time() + 3600,
            'origins' => $this->_getAllowedOrigins(),
            'typ' => 'savetowallet',
            'payload' => [
                $payloadKey => [
                    ['id' => $objectId],
                ],
            ],
        ];

        $token = JWT::encode($claims, $serviceAccount['private_key'], 'RS256');

        return "https://pay.google.com/gp/v/save/{$token}";
    }

    private function _getAllowedOrigins(): array
    {
        $siteUrl = UrlHelper::siteUrl();
        $parsed = parse_url($siteUrl);

        $origin = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $origin .= ':' . $parsed['port'];
        }

        return [$origin];
    }
}
