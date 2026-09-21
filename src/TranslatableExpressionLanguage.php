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
}
