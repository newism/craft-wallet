<?php

namespace newism\wallet\passbook;

use Passbook\Pass;

/**
 * Extends the eo/passbook Pass to support arbitrary extra keys in the pass JSON.
 *
 * The eo/passbook library's `toArray()` method only serializes a fixed list of
 * known properties (serialNumber, description, barcode, etc.). Apple regularly
 * adds new pass.json keys (semantics, relevantDates, preferredStyleSchemes, etc.)
 * that the library doesn't support yet.
 *
 * This class allows generators to inject any additional top-level keys into the
 * pass JSON via `setExtras()`. These extras are merged into the output of
 * `toArray()`, which means they are included when PassFactory signs and packages
 * the .pkpass file.
 *
 * Usage in a generator:
 *
 *   $pass = new ExtendableEventTicket($serialNumber, $description);
 *   $pass->setExtras([
 *       'semantics' => [
 *           'eventType' => 'PKEventTypeGeneric',
 *           'eventStartDate' => '2026-04-04T19:00:00Z',
 *           'eventName' => 'Concert',
 *           'venueName' => 'Arena',
 *       ],
 *       'preferredStyleSchemes' => ['posterEventTicket', 'eventTicket'],
 *       'relevantDates' => [
 *           ['startDate' => '2026-04-04T19:00:00Z', 'endDate' => '2026-04-04T23:00:00Z'],
 *       ],
 *   ]);
 *
 * The extras will be included in the signed pass.json alongside the standard fields.
 */
class ExtendablePass extends Pass
{
    /**
     * Additional top-level keys to include in the pass JSON.
     * These are merged after the standard eo/passbook properties.
     */
    private array $_extras = [];

    /**
     * Sets additional top-level keys for the pass JSON.
     *
     * Keys set here will be merged into the output of toArray() and
     * included in the signed .pkpass file. Use this for Apple pass
     * features that the eo/passbook library doesn't natively support
     * (e.g. semantics, relevantDates, preferredStyleSchemes).
     *
     * @param array $extras Key-value pairs to merge into pass.json
     */
    public function setExtras(array $extras): void
    {
        $this->_extras = $extras;
    }

    /**
     * Adds or updates a single extra key.
     *
     * @param string $key The top-level pass.json key
     * @param mixed $value The value for that key
     */
    public function setExtra(string $key, mixed $value): void
    {
        $this->_extras[$key] = $value;
    }

    /**
     * Returns the extras array.
     */
    public function getExtras(): array
    {
        return $this->_extras;
    }

    /**
     * Extends toArray() to include extras after the standard properties.
     *
     * This is called by PassFactory::serialize() during package(),
     * so extras are included in the signed pass.json.
     */
    public function toArray()
    {
        $base = parent::toArray();
        if (empty($this->_extras)) {
            return $base;
        }
        return array_merge($base, $this->_extras);
    }
}
