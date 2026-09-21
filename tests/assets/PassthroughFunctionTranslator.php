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

namespace sad_spirit\el2sql\tests\assets;

use sad_spirit\el2sql\ExtraFragmentsProvider;
use sad_spirit\el2sql\FunctionTranslator;
use sad_spirit\pg_builder\SelectCommon;
use sad_spirit\pg_builder\nodes\{
    QualifiedName,
    ScalarExpression,
    expressions\FunctionExpression,
    lists\FunctionArgumentList
};

/**
 * Translates a function invocation to a function invocation with the same (translated) arguments
 */
readonly class PassthroughFunctionTranslator implements FunctionTranslator, ExtraFragmentsProvider
{
    public function __construct(private string $name, private array $extraFragments = [])
    {
    }

    public function getExtraFragments(): iterable
    {
        return $this->extraFragments;
    }

    public function translate(SelectCommon|ScalarExpression ...$arguments): ScalarExpression
    {
        return new FunctionExpression(new QualifiedName($this->name), new FunctionArgumentList($arguments));
    }
}
