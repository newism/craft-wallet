<?php

namespace newism\wallet\controllers;

use Craft;
use craft\web\Controller;
use Exception;
use newism\wallet\Wallet;
use OpenSSLAsymmetricKey;
use RuntimeException;
use yii\web\Response;

/**
 * Google Wallet Webhook Controller
 *
 * Handles callbacks from Google Wallet for pass events (save/delete).
 * Verifies signatures using ECv2SigningOnly protocol.
 *
 * @see https://developers.google.com/wallet/generic/use-cases/use-callbacks-for-saves-and-deletions
 */
class GoogleWebhookController extends Controller
{
    private const GOOGLE_PUBLIC_KEYS_URL = 'https://pay.google.com/gp/m/issuer/keys';
    private const GOOGLE_SENDER_ID = 'GooglePayPasses';
    private const PROTOCOL_VERSION = 'ECv2SigningOnly';
    private const PUBLIC_KEYS_CACHE_DURATION = 3600;

    protected array|bool|int $allowAnonymous = true;
    public $enableCsrfValidation = false;

    /**
     * Handles the webhook callback from Google Wallet.
     */
    public function actionCallback(): Response
    {
        $request = Craft::$app->getRequest();

        if (!$request->getIsPost()) {
            return $this->asJson(['status' => 'error', 'message' => 'POST required']);
        }

        $payload = json_decode($request->getRawBody(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->asJson(['status' => 'error', 'message' => 'Invalid JSON']);
        }

        // Verify signature and extract message
        $message = $this->_verifyCallbackSignature($payload);
        if ($message === null) {
            return $this->asJson(['status' => 'error', 'message' => 'Signature verification failed']);
        }

        // Log the verified callback
        Craft::info(sprintf(
            'Google Wallet callback: %s %s',
            $message['eventType'] ?? 'unknown',
            $message['objectId'] ?? ''
        ), Wallet::LOG_GOOGLE_WEBHOOK);

        return $this->asJson(['status' => 'ok']);
    }

    /**
     * Verifies a signed callback message from Google Wallet.
     *
     * @see https://notificare.com/blog/2022/07/08/Handle-Google-Wallet-callbacks-with-nodejs/
     */
    private function _verifyCallbackSignature(array $payload): ?array
    {
        try {
            $signedMessage = $this->_verifyMessageSignature($payload);

            $message = json_decode($signedMessage, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Craft::warning('Google Wallet callback: invalid signedMessage JSON', Wallet::LOG_GOOGLE_WEBHOOK);
                return null;
            }

            // Check expiration
            $expTimeMillis = $message['expTimeMillis'] ?? 0;
            if ($expTimeMillis > 0 && $expTimeMillis < (time() * 1000)) {
                Craft::warning('Google Wallet callback: message expired', Wallet::LOG_GOOGLE_WEBHOOK);
                return null;
            }

            return $message;
        } catch (Exception $e) {
            Craft::warning('Google Wallet callback: ' . $e->getMessage(), Wallet::LOG_GOOGLE_WEBHOOK);
            return null;
        }
    }

    /**
     * Verifies the message signature using the intermediate signing key.
     */
    private function _verifyMessageSignature(array $payload): string
    {
        $signature = base64_decode($payload['signature'] ?? '');
        $signedMessage = $payload['signedMessage'] ?? '';

        if (!$signature || !$signedMessage) {
            throw new RuntimeException('missing signature or signedMessage');
        }

        $recipientId = Wallet::getInstance()->getSettings()->google->issuerId;
        $signedBytes = $this->_toLengthValue(
            self::GOOGLE_SENDER_ID,
            $recipientId,
            self::PROTOCOL_VERSION,
            $signedMessage
        );

        $intermediatePublicKey = $this->_verifyIntermediateSigningKey($payload);

        if (openssl_verify($signedBytes, $signature, $intermediatePublicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('could not verify message signature');
        }

        return $signedMessage;
    }

    /**
     * Verifies the intermediate signing key against Google's public keys.
     */
    private function _verifyIntermediateSigningKey(array $payload): OpenSSLAsymmetricKey
    {
        $intermediateSigningKey = $payload['intermediateSigningKey'] ?? null;
        if (!$intermediateSigningKey) {
            throw new RuntimeException('missing intermediateSigningKey');
        }

        $signedKeyAsString = $intermediateSigningKey['signedKey'] ?? '';
        $signatures = $intermediateSigningKey['signatures'] ?? [];

        if (!$signedKeyAsString || empty($signatures)) {
            throw new RuntimeException('invalid intermediateSigningKey structure');
        }

        // Parse and check expiration before expensive crypto operations
        $signedKey = json_decode($signedKeyAsString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('invalid signedKey JSON');
        }

        $nowMillis = time() * 1000;
        $keyExpiration = (int)($signedKey['keyExpiration'] ?? 0);
        if ($keyExpiration > 0 && $keyExpiration < $nowMillis) {
            throw new RuntimeException('intermediate signing key expired');
        }

        // Fetch Google's public keys
        $googlePublicKeys = $this->_fetchGooglePublicKeys();
        if (!$googlePublicKeys) {
            throw new RuntimeException('could not fetch Google public keys');
        }

        // Build signed bytes and decode signatures
        $signedBytes = $this->_toLengthValue(self::GOOGLE_SENDER_ID, self::PROTOCOL_VERSION, $signedKeyAsString);
        $decodedSignatures = array_map('base64_decode', $signatures);

        // Try to verify with any valid Google key
        foreach ($googlePublicKeys as $keyData) {
            $googleKeyExpiration = (int)($keyData['keyExpiration'] ?? 0);
            if ($googleKeyExpiration > 0 && $googleKeyExpiration < $nowMillis) {
                continue;
            }

            $publicKey = openssl_pkey_get_public($this->_convertEcKeyToPem($keyData['keyValue']));
            if (!$publicKey) {
                continue;
            }

            foreach ($decodedSignatures as $sigBytes) {
                if (openssl_verify($signedBytes, $sigBytes, $publicKey, OPENSSL_ALGO_SHA256) === 1) {
                    $intermediateKey = openssl_pkey_get_public($this->_convertEcKeyToPem($signedKey['keyValue']));
                    if (!$intermediateKey) {
                        throw new RuntimeException('invalid intermediate key value');
                    }
                    return $intermediateKey;
                }
            }
        }

        throw new RuntimeException('could not verify intermediate signing key');
    }

    /**
     * Creates a length-prefixed byte string (little-endian).
     */
    private function _toLengthValue(string ...$chunks): string
    {
        $result = '';
        foreach ($chunks as $chunk) {
            $result .= pack('V', strlen($chunk)) . $chunk;
        }
        return $result;
    }

    /**
     * Fetches Google's public keys for signature verification.
     */
    private function _fetchGooglePublicKeys(): ?array
    {
        $cacheKey = 'google_wallet_public_keys';
        $cached = Craft::$app->getCache()->get($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        try {
            $client = Craft::createGuzzleClient(['timeout' => 10]);
            $response = $client->get(self::GOOGLE_PUBLIC_KEYS_URL);
            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['keys'])) {
                return null;
            }

            Craft::$app->getCache()->set($cacheKey, $data['keys'], self::PUBLIC_KEYS_CACHE_DURATION);
            return $data['keys'];
        } catch (Exception $e) {
            Craft::error("Failed to fetch Google public keys: {$e->getMessage()}", Wallet::LOG_GOOGLE_WEBHOOK);
            return null;
        }
    }

    /**
     * Converts a base64-encoded EC public key to PEM format.
     */
    private function _convertEcKeyToPem(string $base64Key): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode(base64_decode($base64Key)), 64, "\n")
            . "-----END PUBLIC KEY-----";
    }
}
