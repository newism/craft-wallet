<?php

namespace newism\wallet\passbook;

/**
 * EventTicket pass type with support for extras.
 *
 * Use this instead of \Passbook\Type\EventTicket when you need to include
 * Apple pass features not supported by the eo/passbook library
 * (semantics, relevantDates, preferredStyleSchemes, etc.).
 */
class ExtendableEventTicket extends ExtendablePass
{
    protected $type = 'eventTicket';
}
