<?php

namespace newism\wallet\events;

use craft\elements\User;
use newism\wallet\models\Pass;
use yii\base\Event;

/**
 * Event triggered when building a Google Wallet pass object payload.
 *
 * Listeners can customise the payload array (card title, header, barcode, etc.).
 * The plugin will always force id, classId, and state after the event.
 */
class BuildGooglePassObjectEvent extends Event
{
    /**
     * The pass object payload array.
     */
    public array $payload;

    /**
     * The pass record.
     */
    public Pass $pass;

    /**
     * The Craft user the pass is for.
     */
    public User $user;
}
