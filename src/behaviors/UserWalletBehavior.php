<?php

namespace newism\wallet\behaviors;

use craft\elements\User;
use newism\wallet\models\Pass;
use yii\base\Behavior;

/**
 * Wallet behavior for User elements.
 *
 * Exposes wallet pass data directly on User objects for use in
 * templates and GraphQL.
 *
 * @property User $owner
 */
class UserWalletBehavior extends Behavior
{
    /**
     * Returns true if the user has any wallet passes.
     */
    public function hasPasses(): bool
    {
        return Pass::find()->userId($this->owner->id)->exists();
    }

    /**
     * Returns all passes for this user, optionally filtered by generator.
     *
     * @return Pass[]
     */
    public function getPasses(?string $generatorHandle = null): array
    {
        $query = Pass::find()->userId($this->owner->id);
        if ($generatorHandle !== null) {
            $query->generatorHandle($generatorHandle);
        }
        return $query->all();
    }

    /**
     * Returns a single pass for a specific generator and optional source.
     */
    public function getPass(
        string $generatorHandle = 'membership',
        ?int $sourceId = null,
        ?int $sourceIndex = null
    ): ?Pass
    {
        $query = Pass::find()
            ->userId($this->owner->id)
            ->generatorHandle($generatorHandle)
            ->sourceId($sourceId)
            ->sourceIndex($sourceIndex)
        ;

        return $query->one();
    }

    /**
     * Checks if the user has any pass for a generator + source.
     *
     * Usage in Twig:
     *   {{ currentUser.hasPass('event-ticket') }}
     *   {{ currentUser.hasPass('membership') }}
     *   {{ currentUser.hasPass() }}
     */
    public function hasPass(
        string $generatorHandle = 'membership',
        ?int $sourceId = null,
        ?int $sourceIndex = null
    ): bool
    {
        $query = Pass::find()
            ->userId($this->owner->id)
            ->generatorHandle($generatorHandle)
            ->sourceId($sourceId)
            ->sourceIndex($sourceIndex)
        ;
        return $query->exists();
    }
}
