<?php

namespace newism\wallet\models;

use Craft;
use craft\config\BaseConfig;

/**
 * Google Wallet settings.
 *
 * Environment variables with prefix WALLET_GOOGLE_ are automatically applied.
 * E.g. WALLET_GOOGLE_ISSUER_ID overrides the issuerId property.
 */
class GoogleSettings extends BaseConfig
{
    // Identifiers
    public ?string $issuerId = null;
    public ?string $orgName = null;
    public string $classSuffix = 'membership';

    // Credentials
    public ?string $serviceAccountJsonBase64 = null;
    public ?string $serviceAccountJsonPath = '@root/config/wallet/google/service-account.json';

    // Colors (hex)
    public string $backgroundColor = '#ffffff';

    // Field labels / text
    public string $subHeader = 'Member';
    public string $memberIdLabel = 'Member ID';

    // Image paths (relative to @root, or absolute)
    public string $logoPath = '@root/config/wallet/google/logo.png';
    public string $heroPath = '@root/config/wallet/google/hero.png';

    // Fluent setters

    public function issuerId(?string $value): static
    {
        $this->issuerId = $value;
        return $this;
    }

    public function orgName(?string $value): static
    {
        $this->orgName = $value;
        return $this;
    }

    public function classSuffix(string $value): static
    {
        $this->classSuffix = $value;
        return $this;
    }

    public function serviceAccountJsonBase64(?string $value): static
    {
        $this->serviceAccountJsonBase64 = $value;
        return $this;
    }

    public function serviceAccountJsonPath(?string $value): static
    {
        $this->serviceAccountJsonPath = $value;
        return $this;
    }

    public function backgroundColor(string $value): static
    {
        $this->backgroundColor = $value;
        return $this;
    }

    public function subHeader(string $value): static
    {
        $this->subHeader = $value;
        return $this;
    }

    public function memberIdLabel(string $value): static
    {
        $this->memberIdLabel = $value;
        return $this;
    }

    public function logoPath(string $value): static
    {
        $this->logoPath = $value;
        return $this;
    }

    public function heroPath(string $value): static
    {
        $this->heroPath = $value;
        return $this;
    }

    public function validate($attributeNames = null, $clearErrors = true): bool
    {
        $result = parent::validate($attributeNames, $clearErrors);

        // Service account: either base64 env var or file on disk
        if (!$this->serviceAccountJsonBase64 && !file_exists(Craft::getAlias($this->serviceAccountJsonPath))) {
            $this->addError('', "Service Account is not valid. Place file at {$this->serviceAccountJsonPath} or configure serviceAccountJsonBase64.");
            $result = false;
        }

        return $result;
    }

    protected function defineRules(): array
    {
        return [
            [['issuerId'], 'required'],
            [['backgroundColor'], 'required'],
            [['backgroundColor'], 'match', 'pattern' => '/^#[0-9a-fA-F]{6}$/', 'message' => '{attribute} must be a valid hex color (e.g. #ff0000).'],
            [['subHeader', 'memberIdLabel'], 'required'],
            [['serviceAccountJsonPath', 'logoPath', 'heroPath'], function($attribute, $params, $validator, $current) {
                if ($current === null) {
                    return;
                }
                $path = Craft::getAlias($current);
                if (!file_exists($path)) {
                    $label = $this->getAttributeLabel($attribute);
                    $this->addError($attribute, "{$label} file not found");
                }
            }],
        ];
    }
}
