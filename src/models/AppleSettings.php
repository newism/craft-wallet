<?php

namespace newism\wallet\models;

use Craft;
use craft\config\BaseConfig;

/**
 * Apple Wallet settings.
 *
 * Environment variables with prefix WALLET_APPLE_ are automatically applied.
 * E.g. WALLET_APPLE_PASS_TYPE_ID overrides the passTypeId property.
 */
class AppleSettings extends BaseConfig
{
    // Identifiers
    public ?string $passTypeId = null;
    public ?string $teamId = null;
    public ?string $orgName = null;

    // Certificates
    public ?string $p12Password = null;
    public ?string $p12Base64 = null;
    public ?string $p12Path = '@root/config/wallet/apple/certificate.p12';
    public string $wwdrCertPath = '@root/config/wallet/apple/applewwdrca.pem';

    // Colors (hex)
    public string $backgroundColor = '#ffffff';
    public string $foregroundColor = '#184cef';
    public string $labelColor = '#000000';

    // Field labels
    public string $memberIdLabel = 'Member ID';
    public string $nameLabel = 'Name';

    // Image paths (relative to @root, or absolute)
    public string $iconPath = '@root/config/wallet/apple/icon.png';
    public string $icon2xPath = '@root/config/wallet/apple/icon@2x.png';
    public string $logoPath = '@root/config/wallet/apple/logo.png';
    public string $logo2xPath = '@root/config/wallet/apple/logo@2x.png';
    public string $stripPath = '@root/config/wallet/apple/strip.png';
    public string $strip2xPath = '@root/config/wallet/apple/strip@2x.png';

    // Fluent setters

    public function passTypeId(?string $value): static
    {
        $this->passTypeId = $value;
        return $this;
    }

    public function teamId(?string $value): static
    {
        $this->teamId = $value;
        return $this;
    }

    public function orgName(?string $value): static
    {
        $this->orgName = $value;
        return $this;
    }

    public function p12Password(?string $value): static
    {
        $this->p12Password = $value;
        return $this;
    }

    public function p12Base64(?string $value): static
    {
        $this->p12Base64 = $value;
        return $this;
    }

    public function p12Path(?string $value): static
    {
        $this->p12Path = $value;
        return $this;
    }

    public function wwdrCertPath(string $value): static
    {
        $this->wwdrCertPath = $value;
        return $this;
    }

    public function backgroundColor(string $value): static
    {
        $this->backgroundColor = $value;
        return $this;
    }

    public function foregroundColor(string $value): static
    {
        $this->foregroundColor = $value;
        return $this;
    }

    public function labelColor(string $value): static
    {
        $this->labelColor = $value;
        return $this;
    }

    public function memberIdLabel(string $value): static
    {
        $this->memberIdLabel = $value;
        return $this;
    }

    public function nameLabel(string $value): static
    {
        $this->nameLabel = $value;
        return $this;
    }

    public function iconPath(string $value): static
    {
        $this->iconPath = $value;
        return $this;
    }

    public function icon2xPath(string $value): static
    {
        $this->icon2xPath = $value;
        return $this;
    }

    public function logoPath(string $value): static
    {
        $this->logoPath = $value;
        return $this;
    }

    public function logo2xPath(string $value): static
    {
        $this->logo2xPath = $value;
        return $this;
    }

    public function stripPath(string $value): static
    {
        $this->stripPath = $value;
        return $this;
    }

    public function strip2xPath(string $value): static
    {
        $this->strip2xPath = $value;
        return $this;
    }

    public function validate($attributeNames = null, $clearErrors = true): bool
    {
        $result = parent::validate($attributeNames, $clearErrors);

        // P12 certificate: either base64 env var or file on disk
        if (!$this->p12Base64 && !file_exists(Craft::getAlias($this->p12Path))) {
            $this->addError('', "P12 certificate is not valid. Place file at $this->p12Path or configure p12Base64.");
            $result = false;
        }

        return $result;
    }

    protected function defineRules(): array
    {
        return [
            [['passTypeId', 'teamId', 'orgName'], 'required'],
            [['p12Password'], 'required'],
            [['backgroundColor', 'foregroundColor', 'labelColor'], 'required'],
            [['backgroundColor', 'foregroundColor', 'labelColor'], 'match', 'pattern' => '/^#[0-9a-fA-F]{6}$/', 'message' => '{attribute} must be a valid hex color (e.g. #ff0000).'],
            [['memberIdLabel', 'nameLabel'], 'required'],
            [['p12Path', 'wwdrCertPath', 'iconPath', 'icon2xPath', 'logoPath', 'logo2xPath', 'stripPath', 'strip2xPath'], function($attribute, $params, $validator, $current) {
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
