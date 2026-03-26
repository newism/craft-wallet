<?php

namespace newism\wallet\controllers;

use Craft;
use craft\web\Controller;
use newism\wallet\models\Pass;
use newism\wallet\Wallet;
use yii\web\Response;

/**
 * Apple Wallet Webhook Controller
 *
 * Handles Apple Wallet Web Service callbacks.
 * https://developer.apple.com/documentation/walletpasses/adding-a-web-service-to-update-passes
 */
class AppleWebhookController extends Controller
{
    /**
     * @inheritdoc
     * All webhook endpoints allow anonymous access
     */
    protected array|bool|int $allowAnonymous = true;

    /**
     * @inheritdoc
     * Disable CSRF validation for webhooks (Apple doesn't send CSRF tokens)
     */
    public $enableCsrfValidation = false;

    /**
     * Register or unregister a device for pass updates.
     * POST = Register, DELETE = Unregister
     *
     * Route: /wallet/apple/v1/devices/{deviceId}/registrations/{passTypeId}/{serialNumber}
     *
     * @param string $deviceId Device library identifier
     * @param string $passTypeId Pass type identifier
     * @param string $serialNumber Pass serial number
     * @return Response
     */
    public function actionDeviceRegistration(string $deviceId, string $passTypeId, string $serialNumber): Response
    {
        $request = Craft::$app->getRequest();
        $service = Wallet::getInstance()->getApplePassService();

        Craft::info("device-registration called - Method: {$request->method}, Device: {$deviceId}, Pass: {$serialNumber}", Wallet::LOG_APPLE_WEBHOOK);

        // Validate auth token
        $authToken = $this->_getAuthToken();
        if (!$authToken) {
            Craft::warning("device-registration - No auth token provided", Wallet::LOG_APPLE_WEBHOOK);
            return $this->_asRawResponse('', 401);
        }

        // Find the pass by authToken + uid (serialNumber)
        $pass = Pass::find()->authToken($authToken)->uid($serialNumber)->one();
        if (!$pass) {
            Craft::warning("device-registration - Pass not found for token: {$authToken}, type: {$passTypeId}, serial: {$serialNumber}", Wallet::LOG_APPLE_WEBHOOK);
            return $this->_asRawResponse('', 401);
        }

        if ($request->isPost) {
            // Register device
            $body = json_decode($request->getRawBody(), true);
            $pushToken = $body['pushToken'] ?? null;

            Craft::info("Registering device {$deviceId} with push token: " . ($pushToken ? 'yes' : 'no'), Wallet::LOG_APPLE_WEBHOOK);

            $isNew = $service->registerDevice($pass, $deviceId, $pushToken);

            Craft::info("Device registration " . ($isNew ? 'created (201)' : 'already exists (200)'), Wallet::LOG_APPLE_WEBHOOK);
            return $this->_asRawResponse('', $isNew ? 201 : 200);
        }

        if ($request->isDelete) {
            // Unregister device
            Craft::info("Unregistering device {$deviceId}", Wallet::LOG_APPLE_WEBHOOK);
            $service->unregisterDevice($pass, $deviceId);

            return $this->_asRawResponse('', 200);
        }

        return $this->_asRawResponse('', 405);
    }

    /**
     * List passes that have been updated since a given timestamp.
     *
     * Route: /wallet/apple/v1/devices/{deviceId}/registrations/{passTypeId}
     *
     * @param string $deviceId Device library identifier
     * @param string $passTypeId Pass type identifier
     * @return Response
     */
    public function actionListUpdatablePasses(string $deviceId, string $passTypeId): Response
    {
        $request = Craft::$app->getRequest();
        $service = Wallet::getInstance()->getApplePassService();

        $passesUpdatedSince = $request->getQueryParam('passesUpdatedSince');

        Craft::info("list-updatable-passes - Device: {$deviceId}, Type: {$passTypeId}, Since: {$passesUpdatedSince}", Wallet::LOG_APPLE_WEBHOOK);

        $result = $service->getUpdatablePasses($deviceId, $passesUpdatedSince);

        if (empty($result['serialNumbers'])) {
            Craft::info("list-updatable-passes - No updates (204)", Wallet::LOG_APPLE_WEBHOOK);
            return $this->_asRawResponse('', 204);
        }

        Craft::info("list-updatable-passes - Returning " . count($result['serialNumbers']) . " passes", Wallet::LOG_APPLE_WEBHOOK);
        return $this->asJson([
            'serialNumbers' => $result['serialNumbers'],
            'lastUpdated' => (string)$result['lastUpdated'],
        ]);
    }

    /**
     * Get an updated pass.
     *
     * Route: /wallet/apple/v1/passes/{passTypeId}/{serialNumber}
     *
     * @param string $passTypeId Pass type identifier
     * @param string $serialNumber Pass serial number
     * @return Response
     */
    public function actionGetPass(string $passTypeId, string $serialNumber): Response
    {
        $service = Wallet::getInstance()->getApplePassService();

        Craft::info("get-pass - Type: {$passTypeId}, Serial: {$serialNumber}", Wallet::LOG_APPLE_WEBHOOK);

        // Validate auth token
        $authToken = $this->_getAuthToken();
        if (!$authToken) {
            Craft::warning("get-pass - No auth token provided", Wallet::LOG_APPLE_WEBHOOK);
            return $this->_asRawResponse('', 401);
        }

        // Find the pass by authToken + uid (serialNumber)
        $pass = Pass::find()->authToken($authToken)->uid($serialNumber)->one();
        if (!$pass) {
            Craft::warning("get-pass - Pass not found", Wallet::LOG_APPLE_WEBHOOK);
            return $this->_asRawResponse('', 401);
        }

        // Check If-Modified-Since header
        $passLastUpdated = $pass->lastUpdatedAt?->getTimestamp() ?? 0;
        $ifModifiedSince = Craft::$app->getRequest()->getHeaders()->get('If-Modified-Since');

        if ($ifModifiedSince) {
            $ifModifiedSinceTime = strtotime($ifModifiedSince);
            if ($ifModifiedSinceTime && $passLastUpdated <= $ifModifiedSinceTime) {
                Craft::info("get-pass - Not modified since {$ifModifiedSince}, returning 304", Wallet::LOG_APPLE_WEBHOOK);
                return $this->_asRawResponse('', 304);
            }
        }

        // Generate the .pkpass file
        $pkPass = $service->generatePkpass($pass);

        Craft::info("get-pass - Returning pass for user {$pass->userId}", Wallet::LOG_APPLE_WEBHOOK);

        return $this->response->sendFile(
            $pkPass->getRealPath(),
            options: ['mimeType' => 'application/vnd.apple.pkpass'],
        );
    }

    /**
     * Log endpoint for Wallet app debugging.
     *
     * Route: /wallet/apple/v1/log
     *
     * @return Response
     */
    public function actionLog(): Response
    {
        $request = Craft::$app->getRequest();
        $body = json_decode($request->getRawBody(), true);

        // Log the messages for debugging
        if (!empty($body['logs'])) {
            foreach ($body['logs'] as $log) {
                Craft::warning('[Apple Wallet Log] ' . $log, Wallet::LOG_APPLE_WEBHOOK);
            }
        }

        return $this->_asRawResponse('', 200);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Extract auth token from Authorization header.
     * Format: "ApplePass <token>"
     *
     * @return string|null
     */
    private function _getAuthToken(): ?string
    {
        $authHeader = Craft::$app->getRequest()->getHeaders()->get('Authorization');

        if (!$authHeader) {
            return null;
        }

        if (!preg_match('/^\s*ApplePass\s+(\S+)\s*$/', $authHeader, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Return a raw response with just a status code.
     *
     * @param string $body Response body
     * @param int $statusCode HTTP status code
     * @return Response
     */
    private function _asRawResponse(string $body, int $statusCode): Response
    {
        $response = Craft::$app->getResponse();
        $response->statusCode = $statusCode;
        $response->format = Response::FORMAT_RAW;
        $response->data = $body;

        return $response;
    }
}
