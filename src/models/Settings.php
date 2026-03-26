<?php

namespace newism\wallet\models;

use craft\base\Model;

/**
 * Wallet plugin settings.
 *
 * Contains `apple` and `google` sub-models for platform-specific configuration.
 * Supports config file overrides via `config/wallet.php` using either
 * array or fluent syntax.
 */
class Settings extends Model
{
    public AppleSettings $apple;
    public GoogleSettings $google;

    public function __construct($config = [])
    {
        // Hydrate sub-models from arrays (config file array style)
        // Fluent-style objects pass through as-is
        if (isset($config['apple']) && is_array($config['apple'])) {
            $config['apple'] = new AppleSettings($config['apple']);
        }

        if (isset($config['google']) && is_array($config['google'])) {
            $config['google'] = new GoogleSettings($config['google']);
        }

        parent::__construct($config);
    }

    public function init(): void
    {
        parent::init();

        if (!isset($this->apple)) {
            $this->apple = new AppleSettings();
        }
        if (!isset($this->google)) {
            $this->google = new GoogleSettings();
        }
    }

    protected function defineRules(): array
    {
        return [
            [['apple', 'google'], 'required'],
        ];
    }
}
