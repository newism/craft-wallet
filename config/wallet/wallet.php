<?php

/**
 * Wallet plugin config.
 *
 * Environment variables are applied automatically after this file is loaded.
 * Properties are mapped to env vars using SNAKE_CASE with a prefix:
 *   - Apple Wallet:  WALLET_APPLE_  (e.g. passTypeId → WALLET_APPLE_PASS_TYPE_ID)
 *   - Google Wallet: WALLET_GOOGLE_ (e.g. issuerId → WALLET_GOOGLE_ISSUER_ID)
 *
 * Env vars always win over values set here.
 *
 * @see \newism\wallet\models\Settings
 * @see \newism\wallet\models\AppleSettings
 * @see \newism\wallet\models\GoogleSettings
 */

use newism\wallet\models\AppleSettings;
use newism\wallet\models\GoogleSettings;

return [

    // =========================================================================
    // Apple Wallet
    // =========================================================================

    'apple' => AppleSettings::create()

        // --- Config ---
        ->passTypeId('pass.com.example.membership')
        ->teamId('XXXXXXXXXX')
        ->orgName('Your Organisation')
        ->p12Password('')
        // ->p12Base64('')
        ->p12Path('@root/config/wallet/apple/certificate.p12')
        ->wwdrCertPath('@root/config/wallet/apple/applewwdrca.pem')

        // --- Design ---
        ->backgroundColor('#ffffff')
        ->foregroundColor('#184cef')
        ->labelColor('#000000')
        ->memberIdLabel('Member ID')
        ->nameLabel('Name')

        // --- Design Files ---
        ->iconPath('@root/config/wallet/apple/icon.png')
        ->icon2xPath('@root/config/wallet/apple/icon@2x.png')
        ->logoPath('@root/config/wallet/apple/logo.png')
        ->logo2xPath('@root/config/wallet/apple/logo@2x.png')
        ->stripPath('@root/config/wallet/apple/strip.png')
        ->strip2xPath('@root/config/wallet/apple/strip@2x.png'),

    // =========================================================================
    // Google Wallet
    // =========================================================================

    'google' => GoogleSettings::create()

        // --- Config ---
        ->issuerId('')
        ->orgName('Your Organisation')
        ->classSuffix('membership')
        // ->serviceAccountJsonBase64('')
        ->serviceAccountJsonPath('@root/config/wallet/google/service-account.json')

        // --- Design ---
        ->backgroundColor('#ffffff')
        ->subHeader('Member')
        ->memberIdLabel('Member ID')

        // --- Design Files ---
        ->logoPath('@root/config/wallet/google/logo.png')
        ->heroPath('@root/config/wallet/google/hero.png'),
];
