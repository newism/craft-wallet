<?php

namespace newism\wallet\controllers;

use Craft;
use craft\controllers\EditUserTrait;
use craft\web\Controller;
use newism\wallet\assets\WalletPassesAsset;
use newism\wallet\models\Pass;
use newism\wallet\Wallet;
use newism\wallet\web\assets\cp\WalletCpAsset;
use yii\web\Response;

class UserSettingsController extends Controller
{
    use EditUserTrait;

    public function actionIndex(int $userId = null): ?Response
    {
        $this->requireCpRequest();
        $this->requireLogin();
        $user = $this->editedUser($userId);

        $generators = Wallet::getInstance()
            ->getGeneratorService()
            ->getGenerators();

        $generatorTabs = [];
        foreach ($generators as $generator) {
            $passes = Pass::find()
                ->generatorHandle($generator::handle())
                ->userId($user->id)
                ->all();

            [$template, $variables] = $generator->getUserSettingsContentTemplate($user, $passes);

            $tabId = $generator::handle();
            $generatorTabs[$tabId] = [
                'url' => "#$tabId",
                'label' => $generator::displayName(),
                'template' => $template,
                'variables' => $variables,
            ];
        }

        Craft::$app->getView()->registerAssetBundle(WalletCpAsset::class);

        $response = $this->asEditUserScreen($user, 'wallet-passes');
        $response
            ->tabs($generatorTabs)
            ->contentTemplate('wallet/users/passes', [
                'user' => $user,
                'generatorTabs' => $generatorTabs,
            ]);

        return $response;
    }
}
