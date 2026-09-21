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

namespace sad_spirit\el2sql\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\el2sql\{
    TranslatableExpressionFunction,
    TranslatableExpressionLanguage,
    exceptions\InvalidArgumentException
};
use sad_spirit\el2sql\tests\assets\PassthroughFunctionTranslator;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

class TranslatableExpressionLanguageTest extends TestCase
{
    public function testMissingFunctionTranslator(): void
    {
        $language = new TranslatableExpressionLanguage();
        $language->addFunction(ExpressionFunction::fromPhp('time'));

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage("SQL translator for function 'time' is missing");
        $language->getFunctionTranslator('time');
    }

    public function testExistingFunctionTranslator(): void
    {
        $language = new TranslatableExpressionLanguage();
        $language->addFunction(new TranslatableExpressionFunction(
            'date',
            fn (...$args) => "\date('Y-m-d H:i:s')",
            fn ($p, ...$args) => \date('Y-m-d H:i:s'),
            $translator = new PassthroughFunctionTranslator('date')
        ));

        $this::assertSame($translator, $language->getFunctionTranslator('date'));
    }
}
