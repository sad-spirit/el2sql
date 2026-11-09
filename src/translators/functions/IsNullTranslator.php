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

namespace sad_spirit\el2sql\translators\functions;

use sad_spirit\el2sql\{
    FunctionTranslator,
    exceptions\InvalidArgumentException
};
use sad_spirit\pg_builder\{
    SelectCommon,
    enums\IsPredicate
};
use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    expressions\IsExpression
};

/**
 * Translates is_null() function to IS NULL predicate
 */
final class IsNullTranslator implements FunctionTranslator
{
    use ScalarArguments;

    public function translate(SelectCommon|ScalarExpression ...$arguments): ScalarExpression
    {
        if (1 !== \count($arguments)) {
            throw new InvalidArgumentException('IS NULL accepts only one argument');
        }
        $scalar = $this->convertToScalar(...$arguments);
        return new IsExpression(\reset($scalar), IsPredicate::NULL);
    }
}
