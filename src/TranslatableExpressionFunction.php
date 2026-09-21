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

use Symfony\Component\ExpressionLanguage\ExpressionFunction;

/**
 * Function for ExpressionLanguage providing a means to represent itself in SQL
 */
class TranslatableExpressionFunction extends ExpressionFunction
{
    private readonly FunctionTranslator $translator;

    public function __construct(string $name, callable $compiler, callable $evaluator, FunctionTranslator $translator)
    {
        parent::__construct($name, $compiler, $evaluator);
        $this->translator = $translator;
    }

    /**
     * Returns an object that can translate the function invocation to an SQL construct
     */
    public function getTranslator(): FunctionTranslator
    {
        return $this->translator;
    }
}
