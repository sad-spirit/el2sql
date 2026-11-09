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

use sad_spirit\el2sql\FunctionTranslator;
use sad_spirit\pg_builder\{
    SelectCommon,
    enums\SystemFunctionName
};
use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    expressions\SystemFunctionCall,
    lists\ExpressionList
};

/**
 * Translates min() function to LEAST()
 */
final class LeastTranslator implements FunctionTranslator
{
    use ScalarArguments;

    public function translate(SelectCommon|ScalarExpression ...$arguments): ScalarExpression
    {
        return new SystemFunctionCall(
            SystemFunctionName::LEAST,
            new ExpressionList($this->convertToScalar(...$arguments))
        );
    }
}
