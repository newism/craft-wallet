<?php

namespace newism\wallet\query;

use craft\db\Query;
use craft\elements\User;
use Exception;
use newism\wallet\db\WalletTable;
use newism\wallet\models\Pass;
use newism\wallet\Wallet;

class PassQuery extends Query
{
    private ?int $_id = null;
    private ?string $_uid = null;
    private ?int $_userId = null;
    private ?string $_authToken = null;
    private ?string $_generatorHandle = null;
    private ?int $_sourceId = null;
    private ?int $_sourceIndex = null;
    private ?array $_with = [];

    public function id(int $id): self
    {
        $this->_id = $id;
        return $this;
    }

    public function uid(string $uid): self
    {
        $this->_uid = $uid;
        return $this;
    }

    public function authToken(string $authToken): self
    {
        $this->_authToken = $authToken;
        return $this;
    }

    public function userId(?int $userId): self
    {
        $this->_userId = $userId;
        return $this;
    }

    public function generatorHandle(string $handle): self
    {
        $this->_generatorHandle = $handle;
        return $this;
    }

    public function sourceId(?int $sourceId): self
    {
        $this->_sourceId = $sourceId;
        return $this;
    }

    public function sourceIndex(?int $sourceIndex): self
    {
        $this->_sourceIndex = $sourceIndex;
        return $this;
    }

    public function with(array $array): self
    {
        $this->_with = $array;
        return $this;
    }

    public function one($db = null): mixed
    {
        $row = parent::one($db);

        if (!$row) {
            return null;
        }

        if (in_array('user', $this->_with, true)) {
            $row['user'] = User::find()->id($row['userId'])->one();
        }

        $pass = Pass::fromDbRow($row);

        if (in_array('source', $this->_with, true) && $pass->sourceId !== null && $pass->generatorHandle) {
            try {
                $generator = Wallet::getInstance()->getGeneratorService()->getGeneratorByHandle($pass->generatorHandle);
                $sources = $generator->loadSources([$pass->sourceId]);
                $pass->setSource($sources[$pass->sourceId] ?? null);
            } catch (Exception $e) {
                // Generator not found — skip source loading
            }
        }

        return $pass;
    }

    public function populate($rows): array
    {
        if (in_array('user', $this->_with, true)) {
            $userIds = array_column($rows, 'userId');
            $users = User::find()
                ->id($userIds)
                ->indexBy('id')
                ->all();
            $rows = array_map(function($row) use ($users) {
                $row['user'] = $users[$row['userId']] ?? null;
                return $row;
            }, $rows);
        }

        $passes = array_map(Pass::fromDbRow(...), $rows);

        if (in_array('source', $this->_with, true)) {
            $this->_eagerLoadSources($passes);
        }

        return $passes;
    }

    /**
     * Batch-loads sources for passes, grouped by generator.
     *
     * @param Pass[] $passes
     */
    private function _eagerLoadSources(array $passes): void
    {
        // Group passes by generatorHandle
        $grouped = [];
        foreach ($passes as $pass) {
            if ($pass->sourceId !== null && $pass->generatorHandle) {
                $grouped[$pass->generatorHandle][] = $pass;
            }
        }

        $generatorService = Wallet::getInstance()->getGeneratorService();

        foreach ($grouped as $handle => $handlePasses) {
            try {
                $generator = $generatorService->getGeneratorByHandle($handle);
            } catch (Exception $e) {
                continue; // Skip unknown generators
            }

            $sourceIds = array_unique(array_map(fn(Pass $p) => $p->sourceId, $handlePasses));
            $sources = $generator->loadSources($sourceIds);

            foreach ($handlePasses as $pass) {
                $pass->setSource($sources[$pass->sourceId] ?? null);
            }
        }
    }

    public function prepare($builder): PassQuery
    {
        $this
            ->from([WalletTable::PASSES]);

        if ($this->_id !== null) {
            $this->andWhere(['id' => $this->_id]);
        }

        if ($this->_uid !== null) {
            $this->andWhere(['uid' => $this->_uid]);
        }

        if ($this->_authToken !== null) {
            $this->andWhere(['authToken' => $this->_authToken]);
        }

        if ($this->_userId !== null) {
            $this->andWhere(['userId' => $this->_userId]);
        }

        if ($this->_generatorHandle !== null) {
            $this->andWhere(['generatorHandle' => $this->_generatorHandle]);
        }

        if ($this->_sourceId !== null) {
            $this->andWhere(['sourceId' => $this->_sourceId]);
        }

        if ($this->_sourceIndex !== null) {
            $this->andWhere(['sourceIndex' => $this->_sourceIndex]);
        }

        return parent::prepare($builder);
    }
}
