<?php

namespace newism\wallet\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class WalletCpAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';

    public $depends = [
        CpAsset::class,
    ];

    public $js = [
        'wallet.js',
    ];

    public $css = [
        'wallet.css',
    ];
}
