<?php

namespace newism\wallet\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\InlineEditableFieldInterface;
use craft\base\SortableFieldInterface;
use craft\helpers\Db;
use newism\wallet\fields\data\WalletPassData;
use yii\db\ExpressionInterface;
use yii\db\Schema;

/**
 * Wallet Passes field type.
 *
 * Stores structured wallet pass settings per element.
 * Currently: eligibility toggle. Expandable with color, theme, etc.
 *
 * Query API:
 *   craft.entries().walletPasses({ eligible: true }).all()
 *
 * Twig:
 *   entry.walletPasses.eligible
 */
class WalletPasses extends Field implements InlineEditableFieldInterface, SortableFieldInterface
{
    public static function displayName(): string
    {
        return Craft::t('wallet', 'Wallet Passes');
    }

    public static function icon(): string
    {
        return 'wallet';
    }

    public static function phpType(): string
    {
        return WalletPassData::class;
    }

    public static function dbType(): array|string|null
    {
        return [
            'eligible' => Schema::TYPE_BOOLEAN,
        ];
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): WalletPassData
    {
        return WalletPassData::from($value);
    }

    public function serializeValue(mixed $value, ?ElementInterface $element): ?array
    {
        if ($value instanceof WalletPassData) {
            return $value->serialize();
        }

        return null;
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        if (!$value instanceof WalletPassData) {
            $value = new WalletPassData();
        }

        return Craft::$app->getView()->renderTemplate('_includes/forms/lightswitch.twig', [
            'id' => $this->getInputId() . '-eligible',
            'name' => $this->handle . '[eligible]',
            'on' => $value->eligible,
            'label' => Craft::t('wallet', 'Eligible'),
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return null;
    }

    public function getElementValidationRules(): array
    {
        return [];
    }

    protected function searchKeywords(mixed $value, ElementInterface $element): string
    {
        return '';
    }

    public static function queryCondition(
        array $instances,
        mixed $value,
        array &$params,
    ): ExpressionInterface|array|string|false|null {
        if (is_array($value)) {
            $conditions = [];

            if (isset($value['eligible'])) {
                $valueSql = static::valueSql($instances, 'eligible');
                // Use Craft's built-in boolean param parser — handles
                // cross-database JSON boolean comparison (PostgreSQL, MySQL)
                $conditions[] = Db::parseBooleanParam($valueSql, $value['eligible'], false, Schema::TYPE_JSON);
            }

            if (count($conditions) === 1) {
                return $conditions[0];
            }

            if (count($conditions) > 1) {
                return array_merge(['and'], $conditions);
            }
        }

        return parent::queryCondition($instances, $value, $params);
    }
}
