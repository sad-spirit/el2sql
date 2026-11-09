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
    Expression,
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

    protected function registerFunctions(): void
    {
        parent::registerFunctions();
        $this->addFunction(ExpressionFunction::fromPhp('is_null'));

        $this->functionTranslators['min'] = new translators\functions\LeastTranslator();
        $this->functionTranslators['max'] = new translators\functions\GreatestTranslator();
        $this->functionTranslators['is_null'] = new translators\functions\IsNullTranslator();
    }

    public function register(
        string $name,
        callable $compiler,
        callable $evaluator,
        ?FunctionTranslator $translator = null
    ): void {
        parent::register($name, $compiler, $evaluator);
        if (null !== $translator) {
            $this->functionTranslators[$name] = $translator;
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

    /**
     * Returns an object that performs a translation of a given $expression to SQL
     *
     * The expression is *not* translated immediately as the query containing the translated SQL can be cached
     *
     * @param string[] $knownValues
     */
    public function translate(
        Expression|string $expression,
        array $names = [],
        array $knownValues = []
    ): TranslatedExpression {
        $parsedExpression    = $this->parse($expression, $names);
        $namesFinder         = new ExpressionNamesFinder($parsedExpression);
        $propertyTranslators = [];
        $functionTranslators = [];

        foreach ($namesFinder->getAttributeNames() as [$variableName, $propertyName]) {
            $propertyTranslators[$variableName . '.' . $propertyName] = $this->createPropertyTranslator(
                $variableName,
                $propertyName,
                \in_array($variableName, $knownValues, true)
            );
        }
        foreach ($namesFinder->getFunctionNames() as $functionName) {
            $functionTranslators[$functionName] = $this->getFunctionTranslator($functionName);
        }

        return new TranslatedExpression(
            $parsedExpression,
            $knownValues,
            $propertyTranslators,
            $functionTranslators,
        );
    }
}
