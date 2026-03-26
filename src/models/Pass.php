<?php

namespace newism\wallet\models;

use Craft;
use craft\base\Iconic;
use craft\base\Model;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use DateTime;
use newism\wallet\generators\GeneratorInterface;
use newism\wallet\query\PassQuery;
use newism\wallet\Wallet;

/**
 *
 * @property-write GeneratorInterface $generator
 */
class Pass extends Model implements Iconic
{
    public ?int $id = null;
    public ?string $generatorHandle = null;
    public ?int $userId = null;
    public ?int $sourceId = null;
    public ?int $sourceIndex = 0;
    public ?string $authToken;
    public ?string $applePassJson = null;
    public ?string $googlePassJson = null;
    public ?DateTime $lastUpdatedAt = null;

    public ?string $uid = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    private ?GeneratorInterface $_generator = null;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->uid = $this->uid ?? StringHelper::UUID();
        $this->authToken = $this->authToken ?? StringHelper::UUID();
    }

    public static function find(array $config = []): PassQuery
    {
        return new PassQuery($config);
    }

    public function setGenerator(GeneratorInterface $generator): static
    {
        $this->_generator = $generator;
        $this->generatorHandle = $generator::handle();
        return $this;
    }

    public function getGenerator(): ?GeneratorInterface
    {
        if (!$this->generatorHandle) {
            return null;
        }
        if ($this->_generator !== null) {
            return $this->_generator;
        }
        $this->_generator = Wallet::getInstance()->getGeneratorService()->getGeneratorByHandle($this->generatorHandle);
        return $this->_generator;
    }

    private mixed $_source = null;
    private bool $_sourceLoaded = false;

    public function getSource(): mixed
    {
        if (!$this->_sourceLoaded && $this->_source === null && $this->sourceId !== null) {
            $generator = $this->getGenerator();
            if ($generator) {
                $sources = $generator->loadSources([$this->sourceId]);
                $this->_source = $sources[$this->sourceId] ?? null;
            }
            $this->_sourceLoaded = true;
        }
        return $this->_source;
    }

    public function setSource(mixed $source): void
    {
        $this->_source = $source;
        $this->_sourceLoaded = true;
    }

    private ?User $_user = null;

    public function getUser(): User
    {
        if ($this->_user !== null) {
            return $this->_user;
        }
        $this->_user = User::find()->id($this->userId)->one();
        return $this->_user;
    }

    protected function defineRules(): array
    {
        return [
            [['generator', 'userId'], 'required'],
        ];
    }

    public function getIcon(): ?string
    {
        return 'wallet';
    }

    /**
     * Returns disclosure menu items for this pass in the CP.
     */
    public function getDisclosureMenuItems(bool $canCreate, bool $canDelete, bool $isOwnAccount, string $redirectUrl): array
    {
        $items = [];

        if ($canCreate) {
            if ($isOwnAccount) {
                $items[] = [
                    'label' => Craft::t('wallet', 'Add to Apple Wallet'),
                    'icon' => 'download',
                    'action' => 'wallet/passes/add-to-wallet',
                    'params' => ['passId' => $this->id, 'platform' => 'apple'],
                ];
                $items[] = [
                    'label' => Craft::t('wallet', 'Add to Google Wallet'),
                    'icon' => 'download',
                    'action' => 'wallet/passes/add-to-wallet',
                    'params' => ['passId' => $this->id, 'platform' => 'google'],
                ];
            }

            $items[] = [
                'label' => Craft::t('wallet', 'Copy Apple Wallet URL'),
                'icon' => 'clipboard',
                'attributes' => [
                    'data-wallet-copy-url' => 'wallet/passes/get-add-to-wallet-url',
                    'data-pass-id' => $this->id,
                    'data-platform' => 'apple',
                    'data-copy-label' => Craft::t('wallet', 'Copy this URL to add the pass to Apple Wallet. It expires in 1 hour.'),
                ],
            ];
            $items[] = [
                'label' => Craft::t('wallet', 'Copy Google Wallet URL'),
                'icon' => 'clipboard',
                'attributes' => [
                    'data-wallet-copy-url' => 'wallet/passes/get-add-to-wallet-url',
                    'data-pass-id' => $this->id,
                    'data-platform' => 'google',
                    'data-copy-label' => Craft::t('wallet', 'Copy this URL to add the pass to Google Wallet. It expires in 1 hour.'),
                ],
            ];
        }

        if ($canDelete) {
            $items[] = ['hr' => true];
            $items[] = [
                'label' => Craft::t('wallet', 'Delete'),
                'icon' => 'xmark',
                'destructive' => true,
                'action' => 'wallet/passes/delete',
                'params' => ['id' => $this->id],
                // TODO: Send APNs push before delete to remove pass from devices
                'confirm' => Craft::t('wallet', 'Are you sure you want to delete this pass?'),
                'redirect' => UrlHelper::cpUrl($redirectUrl),
            ];
        }

        return $items;
    }

    /**
     * Creates a model from a database row.
     * Handles DateTime conversion for PostgreSQL compatibility.
     * Child classes should override to set platform-specific properties.
     */
    public static function fromDbRow(array $row): static
    {
        $model = new Pass();
        $model->id = $row['id'];
        $model->uid = $row['uid'];
        $model->userId = $row['userId'];
        $model->generatorHandle = $row['generatorHandle'];
        $model->sourceId = $row['sourceId'];
        $model->sourceIndex = $row['sourceIndex'];
        $model->authToken = $row['authToken'];
        $model->applePassJson = $row['applePassJson'] ?? null;
        $model->googlePassJson = $row['googlePassJson'] ?? null;
        $model->lastUpdatedAt = isset($row['lastUpdatedAt']) ? DateTimeHelper::toDateTime($row['lastUpdatedAt']) : null;
        $model->dateCreated = DateTimeHelper::toDateTime($row['dateCreated']);
        $model->dateUpdated = DateTimeHelper::toDateTime($row['dateUpdated']);
        $model->_user = $row['user'] ?? null;
        return $model;
    }
}
