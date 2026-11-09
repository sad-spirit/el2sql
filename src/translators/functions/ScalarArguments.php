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

use sad_spirit\pg_builder\SelectCommon;
use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    expressions\SubselectExpression
};

trait ScalarArguments
{
    /**
     * Wraps arguments that are instances of SelectCommon into SubselectExpression
     *
     * @return ScalarExpression[]
     */
    protected function convertToScalar(SelectCommon|ScalarExpression ...$arguments): array
    {
        return \array_map(
            fn(SelectCommon|ScalarExpression $argument) => $argument instanceof ScalarExpression
                ? $argument
                : new SubselectExpression($argument),
            $arguments
        );
    }
}
