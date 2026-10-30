<?php

/*
 * This file is part of sad_spirit/el2sql:
 * Translator of Symfony's ExpressionLanguage to Postgres SQL dialect
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\el2sql;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\ExpressionLanguage\{
    ExpressionFunction,
    ExpressionFunctionProviderInterface,
    ExpressionLanguage
};

/**
 * ExpressionLanguage subclass that knows about translating properties and functions to SQL
 */
class TranslatableExpressionLanguage extends ExpressionLanguage
{
    /** @var array<string, FunctionTranslator> */
    private array $functionTranslators = [];
    /** @var PropertyTranslatorFactory[] */
    private array $propertyTranslatorFactories = [];
    private ?VariableMapper $mapper = null;

    /**
     * @param iterable<ExpressionFunctionProviderInterface> $providers
     * @param iterable<PropertyTranslatorFactory> $propertyTranslatorFactories
     */
    public function __construct(
        ?CacheItemPoolInterface $cache = null,
        iterable $providers = [],
        iterable $propertyTranslatorFactories = []
    ) {
        parent::__construct($cache, $providers);
        foreach ($propertyTranslatorFactories as $factory) {
            $this->addPropertyTranslatorFactory($factory);
        }
    }

    public function addFunction(ExpressionFunction $function): void
    {
        parent::addFunction($function);
        if ($function instanceof TranslatableExpressionFunction) {
            $this->functionTranslators[$function->getName()] = $function->getTranslator();
        }
    }

    /**
     * Returns a translator from function invocation to an SQL construct
     */
    public function getFunctionTranslator(string $name): FunctionTranslator
    {
        if (!isset($this->functionTranslators[$name])) {
            throw new exceptions\OutOfBoundsException("SQL translator for function '$name' is missing");
        }
        return $this->functionTranslators[$name];
    }

    /**
     * Sets the mapper from expression variables to class metadata
     */
    public function setVariableMapper(VariableMapper $mapper): void
    {
        $this->mapper = $mapper;
    }

    /**
     * Gets the mapper from expression variables to class metadata
     */
    public function getVariableMapper(): VariableMapper
    {
        if (null === $this->mapper) {
            $property = (new \ReflectionClass(parent::class))
                ->getProperty('cache');

            /** @psalm-suppress MixedArgument */
            $this->mapper = new VariableMapper($property->getValue($this));
        }

        return $this->mapper;
    }

    /**
     * Adds a factory creating translators for properties referenced in the expression
     */
    public function addPropertyTranslatorFactory(PropertyTranslatorFactory $factory): void
    {
        $this->propertyTranslatorFactories[] = $factory;
    }

    /**
     * Creates a translator for a property access in the expression
     *
     * @param bool $knownValue If `true`, a translator for referencing a known value is created, if `false` —
     *                         for looking up a value from the DB
     */
    public function createPropertyTranslator(
        string $variableName,
        string $propertyName,
        bool $knownValue = false
    ): PropertyTranslator {
        $metadata = $this->getVariableMapper()
            ->getPropertyMetadata($variableName, $propertyName);

        foreach ($this->propertyTranslatorFactories as $factory) {
            if (
                null !== $translator = ($knownValue
                    ? $factory->createReferenceTranslator($variableName, $metadata)
                    : $factory->createLookupTranslator($variableName, $metadata))
            ) {
                return $translator;
            }
        }

        throw new exceptions\OutOfBoundsException(\sprintf(
            "SQL translator for %s of '%s.%s' is missing",
            $knownValue ? 'referencing' : 'lookup',
            $variableName,
            $propertyName
        ));
    }
}
