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
use sad_spirit\el2sql\ExpressionNamesFinder;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Parser;

class ExpressionNamesFinderTest extends TestCase
{
    public function testFindsNamesEverywhere(): void
    {
        $finder = new ExpressionNamesFinder(
            (new ExpressionLanguage())->parse(
                '- a.b + c.d * (e.f - foo(bar(a.z), [g.h, i.j], false))',
                [],
                Parser::IGNORE_UNKNOWN_FUNCTIONS | Parser::IGNORE_UNKNOWN_VARIABLES
            )
        );

        $this::assertEquals(['a', 'c', 'e', 'g', 'i'], $finder->getVariableNames());
        $this::assertEquals(['foo', 'bar'], $finder->getFunctionNames());
        $this::assertEquals([
            ['a', 'b'],
            ['a', 'z'],
            ['c', 'd'],
            ['e', 'f'],
            ['g', 'h'],
            ['i', 'j']
        ], $finder->getAttributeNames());
    }


    public function testDoesNotRepeatNames(): void
    {
        $finder = new ExpressionNamesFinder(
            (new ExpressionLanguage())->parse(
                'a.b + a.c + foo(d.e, d.f) + foo(a.b, d.e)',
                [],
                Parser::IGNORE_UNKNOWN_FUNCTIONS | Parser::IGNORE_UNKNOWN_VARIABLES
            )
        );

        $this::assertEquals(['a', 'd'], $finder->getVariableNames());
        $this::assertEquals(['foo'], $finder->getFunctionNames());
        $this::assertEquals([
            ['a', 'b'],
            ['a', 'c'],
            ['d', 'e'],
            ['d', 'f']
        ], $finder->getAttributeNames());
    }
}
