<?php

namespace newism\wallet\controllers;

use craft\helpers\UrlHelper;
use craft\web\Controller;
use yii\web\Response;

class WalletController extends Controller
{
    public function actionIndex(): Response
    {
        return $this->redirect(UrlHelper::cpUrl('wallet/passes'));
    }
}
