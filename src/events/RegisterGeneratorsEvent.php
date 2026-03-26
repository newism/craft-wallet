<?php

namespace newism\wallet\events;

use newism\wallet\generators\GeneratorInterface;
use yii\base\Event;

/**
 * Event for registering custom pass generators.
 *
 * Listeners add generator instances to the $generators array keyed by handle.
 *
 * ```php
 * Event::on(
 *     Wallet::class,
 *     Wallet::EVENT_REGISTER_GENERATORS,
 *     function(RegisterGeneratorsEvent $event) {
 *         $event->generators['event-ticket'] = new MyTicketGenerator();
 *     }
 * );
 * ```
 */
class RegisterGeneratorsEvent extends Event
{
    /**
     * @var array<string, GeneratorInterface>
     * Generator instances keyed by handle.
     */
    public array $generators = [];
}
