<?php

namespace newism\wallet\events;

use craft\elements\User;
use newism\wallet\models\Pass;
use yii\base\Event;

/**
 * Event triggered when building an Apple Wallet pass.
 *
 * Fired after the generator creates and populates the pass, but before
 * the service force-sets passTypeIdentifier, webServiceURL, and authToken.
 *
 * Use this to customise the pass — swap images, change colors, add fields, etc.
 *
 * Example — swap the strip image for event tickets:
 *
 *   Event::on(
 *       ApplePassService::class,
 *       ApplePassService::EVENT_BUILD_APPLE_PASS,
 *       function (BuildApplePassEvent $event) {
 *           if ($event->pass->generatorHandle !== 'verbb-event-ticket') {
 *               return;
 *           }
 *           $purchasedTicket = $event->pass->getSource();
 *           $event->applePass->addImage(new Image('/path/to/strip.png', 'strip'));
 *       }
 *   );
 */
class BuildApplePassEvent extends Event
{
    /**
     * The Apple pass object to customise.
     * May be any pass type (StoreCard, EventTicket, Coupon, etc.).
     */
    public \Passbook\Pass $applePass;

    /**
     * The wallet Pass model — provides generatorHandle, sourceId,
     * getSource(), getUser(), etc.
     */
    public Pass $pass;

    /**
     * The Craft user the pass is for.
     */
    public User $user;
}
