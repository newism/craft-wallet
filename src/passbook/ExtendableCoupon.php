<?php

namespace newism\wallet\passbook;

/**
 * Coupon pass type with support for extras.
 *
 * Use this instead of \Passbook\Type\Coupon when you need to include
 * Apple pass features not supported by the eo/passbook library.
 */
class ExtendableCoupon extends ExtendablePass
{
    protected $type = 'coupon';
}
