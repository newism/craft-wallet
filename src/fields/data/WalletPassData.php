<?php

namespace newism\wallet\fields\data;

use craft\base\Serializable;

/**
 * Value object for the Wallet Passes field.
 *
 * Currently holds eligibility. Expandable with color, theme, etc.
 */
class WalletPassData implements Serializable
{
    public function __construct(
        public readonly bool $eligible = false,
    ) {
    }

    /**
     * Creates an instance from a raw value (array, object, or null).
     */
    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_array($value)) {
            return new self(
                eligible: (bool)($value['eligible'] ?? false),
            );
        }

        return new self();
    }

    public function serialize(): ?array
    {
        return [
            'eligible' => $this->eligible,
        ];
    }
}
