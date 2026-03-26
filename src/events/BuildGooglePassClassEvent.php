<?php

namespace newism\wallet\events;

use newism\wallet\generators\GeneratorInterface;
use yii\base\Event;

/**
 * Event triggered when building the Google Wallet pass class template.
 *
 * Listeners can customise the class payload (card layout, field paths, etc.).
 * The plugin will always force id and callbackOptions after the event.
 *
 * Use $generator->getGooglePassType() to determine the pass type
 * (e.g. 'generic', 'eventTicket') when customising per-type.
 *
 * After changing the class template, re-run:
 *   php craft wallet/setup/google-class
 */
class BuildGooglePassClassEvent extends Event
{
    /**
     * The class payload array.
     */
    public array $payload;

    /**
     * The full class ID (issuerId.classSuffix).
     */
    public string $classId;

    /**
     * The generator that owns this class.
     * Null when called without generator context.
     */
    public ?GeneratorInterface $generator = null;
}
