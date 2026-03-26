<?php

namespace newism\wallet\web\twig;

use newism\wallet\generators\GeneratorInterface;
use newism\wallet\models\Pass;
use newism\wallet\query\PassQuery;
use newism\wallet\Wallet;

/**
 * Twig variable for Wallet Passes templates.
 *
 * Available as `craft.wallet` in Twig templates.
 *
 * Usage:
 * ```twig
 * {# Query builder (like craft.entries()) #}
 * {% set passes = craft.wallet.passes.userId(currentUser.id).generatorHandle('commerce-order').sourceId(lineItem.id).all() %}
 * ```
 */
class WalletVariable
{
    public function getPasses($config = []): PassQuery
    {
        return Pass::find($config);
    }

    /**
     * Returns all registered generators.
     *
     * @return array<string, GeneratorInterface>
     */
    public function getGenerators(): array
    {
        return Wallet::getInstance()->getGeneratorService()->getGenerators();
    }
}
