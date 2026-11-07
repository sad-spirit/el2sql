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
use PHPUnit\Framework\Attributes\DataProvider;
use sad_spirit\el2sql\{
    TranslatedExpression,
    exceptions\OutOfBoundsException,
    exceptions\UnexpectedValueException
};
use sad_spirit\el2sql\tests\assets\{
    ExtraTableFragment,
    PassthroughFunctionTranslator,
    ColumnReferenceTranslator,
    SubselectPropertyTranslator
};
use sad_spirit\pg_builder\SqlBuilderWalker;
use Symfony\Component\ExpressionLanguage\{
    ExpressionFunction,
    ExpressionLanguage,
    Parser
};

class TranslatedExpressionTest extends TestCase
{
    use NormalizeWhitespace;

    private ?ExpressionLanguage $expressionLanguage = null;

    public function setUp(): void
    {
        $this->expressionLanguage = new ExpressionLanguage();
    }

    #[DataProvider('supportedExpressionsProvider')]
    public function testSupportedExpressions(string $expression, string $translated): void
    {
        $this->expressionLanguage->addFunction(ExpressionFunction::fromPhp('time', 'foo'));
        $condition = (new TranslatedExpression(
            $this->expressionLanguage->parse($expression, ['a', 'c']),
            ['a', 'c'],
            [
                'a.b' => new ColumnReferenceTranslator('a', 'b'),
                'c.d' => new SubselectPropertyTranslator('c', 'd')
            ],
            [
                'foo' => new PassthroughFunctionTranslator('foo')
            ]
        ))
            ->getCondition();

        $this::assertStringEqualsStringNormalizingWhitespace(
            $translated,
            $condition->generateExpression()->dispatch(new SqlBuilderWalker())
        );
    }

    public function testExtraFragments(): void
    {
        $tableBar = new ExtraTableFragment('bar', 'table-bar');
        $tableBaz = new ExtraTableFragment('baz', 'table-baz');

        $this->expressionLanguage->addFunction(ExpressionFunction::fromPhp('time', 'foo'));
        $expression = new TranslatedExpression(
            $this->expressionLanguage->parse('foo(a.b)', ['a']),
            ['a'],
            ['a.b' => new ColumnReferenceTranslator('a', 'b', [$tableBar])],
            ['foo' => new PassthroughFunctionTranslator('foo', [$tableBaz])]
        );

        $this::assertSame(
            [$tableBar, $tableBaz],
            \iterator_to_array($expression->getExtraFragments(), false)
        );
    }

    #[DataProvider('unsupportedExpressionsProvider')]
    public function testUnsupportedExpressions(string $expression, string $message): void
    {
        $condition = (new TranslatedExpression(
            $this->expressionLanguage->parse($expression, [], Parser::IGNORE_UNKNOWN_VARIABLES),
            [],
            [],
            []
        ))
            ->getCondition();

        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage($message);
        $condition->generateExpression();
    }

    public function testMissingFunctionTranslator(): void
    {
        $condition = (new TranslatedExpression(
            $this->expressionLanguage->parse('foo()', [], Parser::IGNORE_UNKNOWN_FUNCTIONS),
            [],
            [],
            []
        ))
            ->getCondition();

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('Missing function translator');
        $condition->generateExpression();
    }

    public function testMissingPropertyTranslator(): void
    {
        $condition = (new TranslatedExpression(
            $this->expressionLanguage->parse('a.b', [], Parser::IGNORE_UNKNOWN_VARIABLES),
            [],
            [],
            []
        ))
            ->getCondition();

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('Missing property translator');
        $condition->generateExpression();
    }

    public function testNonScalarExpressionResult(): void
    {
        $condition = (new TranslatedExpression(
            $this->expressionLanguage->parse('a.b', ['a']),
            ['a'],
            ['a.b' => new SubselectPropertyTranslator('a', 'b')],
            []
        ))
            ->getCondition();

        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('Expected an implementation of ScalarExpression');
        $condition->generateExpression();
    }

    public static function supportedExpressionsProvider(): iterable
    {
        yield [
            'a.b !== null or a.b === 1',
            'a.b is distinct from null or a.b is not distinct from 1'
        ];
        yield [
            'a.b != null or a.b == 1',
            'a.b::"text" is distinct from null::"text" or a.b::"text" is not distinct from 1::"text"'
        ];
        yield ['1 + 2', '1 + 2'];
        yield ['2 ** 3 ** 3', '2 ^ (3 ^ 3)'];
        yield ['1 << 2 ^ 34 >> 5 | 6 & 7', '1 << 2 # (34 >> 5) | (6 & 7)'];
        yield ["'foo' ~ 'bar'", "'foo' || 'bar'"];
        yield ['~ 1', '~ 1'];
        yield [
            "a.b in c.d || a.b in ['foo', 'bar']",
            "a.b = any( select d from c ) or a.b = any(array['foo', 'bar'])"
        ];
        yield [
            "a.b not in c.d && a.b not in ['foo', 'bar']",
            "a.b <> all( select d from c ) and a.b <> all(array['foo', 'bar'])"
        ];
        yield ['a.b ?? 42', 'coalesce(a.b, 42)'];
        yield ['false && !true', 'false and not true'];
        yield ["foo(a.b, 1, 't' ~ 'wo')", "foo(a.b, 1, 't' || 'wo')"];
        yield ["'foobarbaz' contains 'bar'", "0 != strpos('foobarbaz', 'bar')"];
        yield ["'foobarbaz' starts with 'foo'", "starts_with('foobarbaz', 'foo')"];
        yield ['5..10', 'array_positions(array_fill(0, array[10 - 5 + 1], array[5]), 0)'];
        yield ["a.b ? 'yes' : 'no'", "case a.b when true then 'yes' else 'no' end"];
    }

    public static function unsupportedExpressionsProvider(): iterable
    {
        yield ['a',     'NameNode'];
        yield ['a.b.c', 'anything but NameNode'];
        yield ['a.b()', 'array index or method call'];
        yield ['a[b]',  'array index or method call'];
    }
}
