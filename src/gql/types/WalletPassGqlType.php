<?php

namespace newism\wallet\gql\types;

use craft\gql\base\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use newism\wallet\models\Pass;

/**
 * GraphQL type for a single wallet pass.
 *
 * Exposes safe fields only — authToken, applePassJson, and googlePassJson
 * are excluded for security.
 */
class WalletPassGqlType extends ObjectType
{
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        if (!$source instanceof Pass) {
            return null;
        }

        return match ($resolveInfo->fieldName) {
            'id' => $source->id,
            'uid' => $source->uid,
            'generatorHandle' => $source->generatorHandle,
            'sourceId' => $source->sourceId,
            'sourceIndex' => $source->sourceIndex,
            'dateCreated' => $source->dateCreated?->format(\DateTime::ATOM),
            'dateUpdated' => $source->dateUpdated?->format(\DateTime::ATOM),
            'lastUpdatedAt' => $source->lastUpdatedAt?->format(\DateTime::ATOM),
            default => null,
        };
    }
}
