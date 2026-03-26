<?php

namespace newism\wallet\controllers;

use Craft;
use craft\base\Chippable;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\AdminTable;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use DateTime;
use newism\wallet\db\WalletTable;
use newism\wallet\generators\GeneratorInterface;
use newism\wallet\models\Pass;
use newism\wallet\Wallet;
use yii\data\Pagination;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * CP Wallet Passes index controller.
 *
 * Provides the main nav "Wallet Passes" section listing all pass records
 * across all users for admin/developer debugging.
 */
class PassesController extends Controller
{
    protected array|int|bool $allowAnonymous = ['add-to-wallet', 'serve-image'];

    public function actionIndex(): Response
    {
        $this->requirePermission(Wallet::PERMISSION_VIEW_OTHER_USERS_PASSES);

        $generators = Wallet::getInstance()->getGeneratorService()->getGenerators();
        $generatorHandle = Craft::$app->getRequest()->getQueryParam('generatorHandle', reset($generators)::handle());
        $pageSidebarNavItems = array_map(function (GeneratorInterface $generator) {
            return [
                'id' => $generator::handle(),
                'label' => $generator::displayName(),
                'url' => UrlHelper::cpUrl('wallet/passes', ['generatorHandle' => $generator::handle()]),
            ];
        }, $generators);

        return $this->asCpScreen()
            ->title(Craft::t('wallet', 'Wallet Passes'))
            ->addCrumb(Wallet::getInstance()->name, 'wallet')
            ->pageSidebarTemplate('_includes/nav.twig', [
                'items' => $pageSidebarNavItems,
                'selectedItem' => $generatorHandle,
            ])
            ->contentTemplate('wallet/passes/index', [
                'generators' => $generators,
                'generatorHandle' => $generatorHandle,
            ]);
    }

    public function actionTableData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission(Wallet::PERMISSION_VIEW_OTHER_USERS_PASSES);

        $request = Craft::$app->getRequest();
        $page = (int)$request->getParam('page', 1);
        $limit = (int)$request->getParam('per_page', 50);
        $search = $request->getParam('search');
        $generatorHandle = $request->getParam('generatorHandle');

        $searchQuery = new Query();
        $searchQuery
            ->from(['p' => WalletTable::PASSES])
            ->select(['p.id'])
            ->where(['p.generatorHandle' => $generatorHandle])
            ->leftJoin(['u' => Table::USERS], '[[u.id]] = [[p.userId]]');

        if ($search) {
            $searchQuery->andWhere([
                'or',
                ['like', 'p.uid', $search],
                ['like', 'p.generatorHandle', $search],
                ['like', 'u.username', $search],
                ['like', 'u.firstName', $search],
                ['like', 'u.lastName', $search],
            ]);
        }

        $countQuery = clone $searchQuery;
        $total = $countQuery->count();

        // Use Pagination helper for pagination
        $pagination = new Pagination([
            'totalCount' => $total,
            'pageSize' => $limit,
            'page' => $page - 1,
        ]);

        $ids = $searchQuery
            ->orderBy(['p.dateCreated' => SORT_DESC])
            ->offset($pagination->offset)
            ->limit($limit)
            ->column();

        $passes = Pass::find()
            ->where(['id' => $ids])
            ->with(['user', 'source'])
            ->all();

        // Format for VueAdminTable
        $data = [];
        $formatter = Craft::$app->getFormatter();
        /** @var Pass $pass */
        foreach ($passes as $pass) {
            $detailUrl = UrlHelper::cpUrl("wallet/passes/view", [
                'id' => $pass->id,
            ]);

            $source = $pass->getSource();
            $sourceHtml = '—';
            if ($source instanceof Chippable) {
                $sourceHtml = Cp::chipHtml($source);
            } elseif ($pass->sourceId) {
                $sourceHtml = (string)$pass->sourceId;
            }

            $data[] = [
                'url' => $detailUrl,
                'title' => $pass->uid,
                'user' => Cp::chipHtml($pass->getUser()),
                'source' => $sourceHtml,
                'sourceIndex' => $pass->sourceIndex ?: '—',
                'dateCreated' => $formatter->asDatetime($pass->dateCreated),
                'dateUpdated' => $formatter->asDatetime($pass->dateUpdated),
            ];
        }

        return $this->asSuccess(
            data: [
                'pagination' => AdminTable::paginationLinks($page, $total, $limit),
                'data' => $data,
            ]
        );
    }

    public function actionView(): Response
    {
        $this->requirePermission(Wallet::PERMISSION_VIEW_OTHER_USERS_PASSES);
        $id = Craft::$app->getRequest()->getRequiredParam('id');

        $pass = Pass::find()
            ->id($id)
            ->with(['user', 'source'])
            ->one();

        if (!$pass) {
            throw new HttpException(404, 'Pass not found.');
        }

        $user = Craft::$app->getUser()->getIdentity();
        $isOwner = $user->id === $pass->userId;

        $actionMenuItems = [];
        $canDelete = $isOwner ?
            $user->can(Wallet::PERMISSION_DELETE_PASSES) :
            $user->can(Wallet::PERMISSION_DELETE_OTHER_USERS_PASSES);

        if ($canDelete) {
            $actionMenuItems[] = [
                'label' => Craft::t('wallet', 'Delete Pass'),
                'action' => 'wallet/passes/delete',
                'params' => [
                    'id' => $id,
                ],
                'destructive' => true,
                'icon' => 'xmark',
                'confirm' => Craft::t('wallet', 'Are you sure you want to delete this pass?'),
            ];
        }

        return $this->asCpScreen()
            ->title($pass->uid)
            ->addCrumb(Wallet::getInstance()->name, 'wallet')
            ->addCrumb(Craft::t('wallet', 'Passes'), 'wallet/passes')
            ->actionMenuItems(fn() => $actionMenuItems)
            ->tabs([
                'overview' => [
                    'label' => 'Pass',
                    'url' => '#overview',
                ],
                'apple' => [
                    'label' => 'Devices',
                    'url' => '#devices',
                ],
                'debug' => [
                    'label' => 'Debug',
                    'url' => '#debug',
                ],
            ])
            ->contentTemplate('wallet/passes/view', [
                'pass' => $pass,
                'devices' => Wallet::getInstance()->getApplePassService()->getDevicesForPass($pass),
            ])
            ->metaSidebarTemplate('wallet/passes/_meta', [
                'pass' => $pass,
            ]);
    }

    public function actionDeviceTableData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission(Wallet::PERMISSION_VIEW_OTHER_USERS_PASSES);

        $passId = (int)Craft::$app->getRequest()->getRequiredParam('passId');

        $service = Wallet::getInstance()->getApplePassService();
        $pass = Pass::find()->id($passId)->one();

        if (!$pass) {
            throw new HttpException(404, 'Pass not found.');
        }

        $devices = $service->getDevicesForPass($pass);
        $formatter = Craft::$app->getFormatter();

        $data = [];
        foreach ($devices as $device) {
            $data[] = [
                'id' => $device->id,
                'title' => $device->deviceLibraryIdentifier,
                'pushToken' => $device->pushToken ? substr($device->pushToken, 0, 12) . '…' : '—',
                'dateCreated' => $formatter->asDatetime($device->dateCreated),
            ];
        }

        return $this->asSuccess(
            data: [
                'data' => $data,
            ]
        );
    }

    public function actionDelete(): Response
    {
        $this->requireLogin();
        $this->requirePostRequest();
        $passId = Craft::$app->getRequest()->getRequiredBodyParam('id');
        $pass = Pass::find()->id($passId)->one();
        if (!$pass) {
            throw new HttpException(404, 'Pass not found.');
        }
        Wallet::getInstance()->getPassService()->deletePass($pass);
        return $this->asSuccess(
            'Pass deleted.',
            redirect: UrlHelper::cpUrl('wallet') . '#apple',
        );
    }

    /**
     * Find-or-create a pass and add it to a wallet platform.
     *
     * Supports two modes:
     * - POST (front-end form or CP): reads params from body
     * - GET with token (shared link): params injected by Craft's token system
     *
     * Required params: generatorHandle, userId, platform (apple|google)
     * Optional params: sourceId, sourceIndex
     */
    public function actionAddToWallet(
        ?string $generatorHandle = null,
        ?int    $userId = null,
        ?string $platform = null,
        ?int    $sourceId = null,
        ?int    $sourceIndex = null,
    ): Response
    {
        $request = Craft::$app->getRequest();

        if ($generatorHandle !== null) {
            // Token-based access: params injected by Craft's token system
            if (!$request->getHadToken()) {
                throw new ForbiddenHttpException();
            }
        } else {
            // Direct access: POST with body params
            $this->requireLogin();
            $this->requirePostRequest();
            $generatorHandle = $request->getRequiredBodyParam('generatorHandle');
            $userId = (int)$request->getRequiredBodyParam('userId');
            $platform = $request->getRequiredBodyParam('platform');
            $sourceId = $request->getBodyParam('sourceId');
            $sourceId = $sourceId ? (int)$sourceId : null;
            $sourceIndex = $request->getBodyParam('sourceIndex');
            $sourceIndex = $sourceIndex ? (int)$sourceIndex : null;
        }

        // Validate generator
        $generator = Wallet::getInstance()->getGeneratorService()->getGeneratorByHandle($generatorHandle);
        if (!$generator) {
            throw new HttpException(400, "Invalid generator handle: $generatorHandle");
        }

        // Find or create the pass
        $pass = Pass::find()
            ->userId($userId)
            ->generatorHandle($generatorHandle)
            ->sourceId($sourceId)
            ->sourceIndex($sourceIndex)
            ->one();

        if (!$pass) {
            $pass = new Pass();
            $pass->userId = $userId;
            $pass->generatorHandle = $generatorHandle;
            $pass->sourceId = $sourceId;
            $pass->sourceIndex = $sourceIndex;

            if (!Wallet::getInstance()->passService->savePass($pass)) {
                throw new HttpException(500, 'Failed to create pass.');
            }
        }

        $result = Wallet::getInstance()->passService->addToWallet($pass, $platform);

        if ($result->isDownload()) {
            return $this->response->sendFile(
                $result->pkPass->getRealPath(),
                options: ['mimeType' => $result->contentType],
            );
        }

        return $this->redirect($result->redirectUrl);
    }

    /**
     * Generates a temporary, single-use token URL for adding a pass to a wallet.
     */
    public function actionGetAddToWalletUrl(): Response
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $generatorHandle = $request->getRequiredBodyParam('generatorHandle');
        $userId = (int)$request->getRequiredBodyParam('userId');
        $platform = $request->getRequiredBodyParam('platform');
        $sourceId = $request->getBodyParam('sourceId');
        $sourceId = $sourceId !== null ? (int)$sourceId : null;
        $sourceIndex = $request->getBodyParam('sourceIndex');
        $sourceIndex = $sourceIndex !== null ? (int)$sourceIndex : null;

        $isOwnAccount = Craft::$app->getUser()->getId() === $userId;
        $this->requirePermission($isOwnAccount
            ? Wallet::PERMISSION_CREATE_PASSES
            : Wallet::PERMISSION_CREATE_OTHER_USERS_PASSES
        );

        $tokenParams = [
            'generatorHandle' => $generatorHandle,
            'userId' => $userId,
            'platform' => $platform,
            'sourceId' => $sourceId,
            'sourceIndex' => $sourceIndex,
        ];

        $token = Craft::$app->getTokens()->createToken(
            ['wallet/passes/add-to-wallet', $tokenParams],
            1,
            new DateTime('+1 hour'),
        );

        if (!$token) {
            return $this->asFailure('Could not generate token URL.');
        }

        $url = UrlHelper::urlWithToken(
            UrlHelper::siteUrl('wallet/passes/add-to-wallet'),
            $token,
        );

        return $this->asJson(['url' => $url]);
    }

    /**
     * Deletes a device registration (admin only).
     */
    public function actionDeleteDevice(): Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $deviceId = Craft::$app->getRequest()->getRequiredBodyParam('deviceId');
        $redirectUrl = Craft::$app->getRequest()->getBodyParam('redirect');

        $service = Wallet::getInstance()->getApplePassService();
        $deleted = $service->deleteDeviceById((int)$deviceId);

        if ($deleted) {
            return $this->asSuccess(
                'Device deleted.',
                redirect: $redirectUrl,
            );
        }

        return $this->asFailure('Device not found.');
    }

    /**
     * Serves images for wallet passes (Apple and Google).
     *
     * Route: wallet/assets/<platform>/<filename>.png
     * Anonymous access required — Google fetches images server-side.
     */
    public function actionServeImage(string $platform, string $filename): Response
    {
        $settings = Wallet::getInstance()->getSettings();

        $platformSettings = match ($platform) {
            'apple' => $settings->apple,
            'google' => $settings->google,
            default => throw new HttpException(404, 'Unknown platform.'),
        };

        // Apple uses @2x suffix: icon@2x → icon2xPath
        $pathProperty = str_replace('@2x', '2x', $filename) . 'Path';

        if (!property_exists($platformSettings, $pathProperty)) {
            throw new HttpException(404, 'Image not found.');
        }

        $imagePath = Craft::getAlias($platformSettings->$pathProperty);

        if (!file_exists($imagePath)) {
            throw new HttpException(404, 'Image not found.');
        }

        return $this->response->sendFile($imagePath, null, ['inline' => true]);
    }
}
