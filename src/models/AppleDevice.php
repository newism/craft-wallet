<?php

namespace newism\wallet\models;

use Craft;
use craft\base\Model;
use craft\helpers\DateTimeHelper;
use DateTime;

/**
 * Apple Wallet device model.
 */
class AppleDevice extends Model
{
    public ?int $id = null;
    public ?string $deviceLibraryIdentifier = null;
    public ?string $pushToken = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;

    public static function fromRow(array $row): self
    {
        $model = new self();
        $model->id = (int)($row['id'] ?? 0);
        $model->deviceLibraryIdentifier = $row['deviceLibraryIdentifier'] ?? null;
        $model->pushToken = $row['pushToken'] ?? null;
        $model->dateCreated = self::_toDateTime($row['dateCreated'] ?? null);
        $model->dateUpdated = self::_toDateTime($row['dateUpdated'] ?? null);
        return $model;
    }

    private static function _toDateTime(mixed $value): ?DateTime
    {
        if ($value === null) {
            return null;
        }
        $dt = DateTimeHelper::toDateTime($value);
        return $dt instanceof DateTime ? $dt : null;
    }

    public function attributeLabels(): array
    {
        return [
            'deviceLibraryIdentifier' => Craft::t('app', 'Device ID'),
            'pushToken' => Craft::t('app', 'Push Token'),
            'dateCreated' => Craft::t('app', 'Registered'),
        ];
    }
}
