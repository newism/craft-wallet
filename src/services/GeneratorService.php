<?php

namespace newism\wallet\services;

use craft\base\Component;
use newism\wallet\events\RegisterGeneratorsEvent;
use newism\wallet\generators\GeneratorInterface;
use newism\wallet\generators\MembershipGenerator;
use newism\wallet\Wallet;
use yii\base\InvalidArgumentException;

/**
 *
 * @property-read array|GeneratorInterface[] $generators
 */
class GeneratorService extends Component
{
    /**
     * @var GeneratorInterface[]|null Cached list of registered generators, indexed by their handle.
     */
    private ?array $_generators = null;

    public function getGenerators(): array
    {
        if ($this->_generators !== null) {
            return $this->_generators;
        }
        $generators = [];

        $event = new RegisterGeneratorsEvent([
            'generators' => $generators,
        ]);
        Wallet::getInstance()->trigger(Wallet::EVENT_REGISTER_GENERATORS, $event);
        $generators = $event->generators;

        $generators = [
            MembershipGenerator::handle() => new MembershipGenerator(),
            ...$generators
        ];

        $this->_generators = $generators;

        return $this->_generators;
    }

    public function getGeneratorByHandle($generatorHandle): GeneratorInterface
    {
        $generators = $this->getGenerators();
        $generator = $generators[$generatorHandle] ?? null;
        if (!$generator) {
            throw new InvalidArgumentException("Generator with handle '$generatorHandle' not found.");
        }
        return $generator;
    }
}
