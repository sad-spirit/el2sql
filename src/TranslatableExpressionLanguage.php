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

use sad_spirit\el2sql\exceptions\InvalidArgumentException;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * ExpressionLanguage subclass that knows about translating properties and functions to SQL
 */
class TranslatableExpressionLanguage extends ExpressionLanguage
{
    /** @var array<string, FunctionTranslator> */
    private array $functionTranslators = [];
    private ?VariableMapper $mapper = null;

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
            throw new InvalidArgumentException("SQL translator for function '$name' is missing");
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
}
