<?php

namespace modules\walletevents;

use Craft;
use craft\elements\User;
use modules\walletevents\generators\DemoEventTicketGenerator;
use newism\wallet\events\RegisterGeneratorsEvent;
use newism\wallet\generators\GeneratorInterface;
use newism\wallet\models\Pass;
use newism\wallet\Wallet;
use yii\base\Event;
use yii\base\Module;

/**
 * Wallet Events Demo Module
 *
 * Demonstrates how to create a custom wallet pass generator.
 * This is a minimal reference implementation for developers.
 *
 * ## Setup
 * 1. Enable this module in config/app.php
 * 2. Run `php craft wallet/setup/google-class` to register the Google class
 *
 * ## Registration
 * Register your generator via EVENT_REGISTER_GENERATORS in your module's init():
 *
 * ```php
 * Event::on(
 *     Wallet::class,
 *     Wallet::EVENT_REGISTER_GENERATORS,
 *     function (RegisterGeneratorsEvent $event) {
 *         $event->generators['my-generator'] = new MyGenerator();
 *     }
 * );
 * ```
 */
class WalletEvents extends Module
{
    public function init(): void
    {
        Craft::setAlias('@modules/walletevents', __DIR__);
        parent::init();

        Event::on(
            Wallet::class,
            Wallet::EVENT_REGISTER_GENERATORS,
            function (RegisterGeneratorsEvent $event) {
                if (!Craft::$app->plugins->isPluginInstalled('events')) {
                    return;
                }
                $event->generators[DemoEventTicketGenerator::handle()] = new DemoEventTicketGenerator();
            }
        );
    }
}
