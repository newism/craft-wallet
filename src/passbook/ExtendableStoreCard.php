<?php

namespace newism\wallet\passbook;

/**
 * StoreCard pass type with support for extras.
 *
 * Use this instead of \Passbook\Type\StoreCard when you need to include
 * Apple pass features not supported by the eo/passbook library.
 */
class ExtendableStoreCard extends ExtendablePass
{
    protected $type = 'storeCard';
}
