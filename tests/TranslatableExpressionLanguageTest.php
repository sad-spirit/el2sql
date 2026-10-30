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
    ClassMetadata,
    PropertyMetadata,
    PropertyTranslatorFactory,
    TranslatableExpressionFunction,
    TranslatableExpressionLanguage,
    TranslatedExpression,
    VariableMapper,
    exceptions\OutOfBoundsException
};
use sad_spirit\el2sql\tests\assets\{
    ClassAttribute,
    ColumnReferenceTranslator,
    MagicEntity,
    ParameterTranslator,
    SimpleEntity,
    PassthroughFunctionTranslator
};
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

class TranslatableExpressionLanguageTest extends TestCase
{
    use Psr6Mocks;

    public function testMissingFunctionTranslator(): void
    {
        $language = new TranslatableExpressionLanguage();
        $language->addFunction(ExpressionFunction::fromPhp('time'));

        $this::expectException(OutOfBoundsException::class);
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

    public function testPropagatesCacheToVariableMapper(): void
    {
        // Putting the wrong metadata into cache
        $simple   = new ClassMetadata(new \ReflectionClass(SimpleEntity::class));
        $language = new TranslatableExpressionLanguage($this->getMockForCacheHit($simple));
        $mapper   = $language->getVariableMapper();

        $mapper->map('magic', MagicEntity::class);
        $this::assertSame($simple, $mapper->getClassMetadata('magic'));
    }

    public function testNoPropertyTranslatorWithoutFactory(): void
    {
        $mapper = $this->createMock(VariableMapper::class);
        $mapper->expects($this::once())
            ->method('getPropertyMetadata')
            ->willReturn(new PropertyMetadata(
                SimpleEntity::class,
                'bar'
            ));

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage("SQL translator for lookup of 'foo.bar' is missing");

        $language = new TranslatableExpressionLanguage();
        $language->setVariableMapper($mapper);
        $language->createPropertyTranslator('foo', 'bar');
    }

    public function testNoPropertyTranslatorWithUnapplicableFactory(): void
    {
        $mapper = $this->createMock(VariableMapper::class);
        $mapper->expects($this::once())
            ->method('getPropertyMetadata')
            ->willReturn($metadata = new PropertyMetadata(
                SimpleEntity::class,
                'bar'
            ));

        $factory = $this->createMock(PropertyTranslatorFactory::class);
        $factory->expects($this::once())
            ->method('createReferenceTranslator')
            ->with('foo', $metadata)
            ->willReturn(null);

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage("SQL translator for referencing of 'foo.bar' is missing");

        $language = new TranslatableExpressionLanguage(null, [], [$factory]);
        $language->setVariableMapper($mapper);
        $language->createPropertyTranslator('foo', 'bar', true);
    }

    public function testUsesFirstApplicablePropertyTranslatorFactory(): void
    {
        $mapper = $this->createMock(VariableMapper::class);
        $mapper->expects($this::exactly(2))
            ->method('getPropertyMetadata')
            ->willReturn($metadata = new PropertyMetadata(
                SimpleEntity::class,
                'bar'
            ));

        $factoryOne = $this->createMock(PropertyTranslatorFactory::class);
        $factoryOne->expects($this::once())
            ->method('createLookupTranslator')
            ->with('foo', $metadata)
            ->willReturn(null);
        $factoryOne->expects($this::once())
            ->method('createReferenceTranslator')
            ->with('foo', $metadata)
            ->willReturn($reference = new ParameterTranslator('foo', 'bar'));

        $factoryTwo = $this->createMock(PropertyTranslatorFactory::class);
        $factoryTwo->expects($this::once())
            ->method('createLookupTranslator')
            ->with('foo', $metadata)
            ->willReturn($lookup = new ColumnReferenceTranslator('foo', 'bar'));
        $factoryTwo->expects($this::never())
            ->method('createReferenceTranslator');

        $language = new TranslatableExpressionLanguage(null, [], [$factoryOne, $factoryTwo]);
        $language->setVariableMapper($mapper);
        $this::assertSame($lookup, $language->createPropertyTranslator('foo', 'bar'));
        $this::assertSame($reference, $language->createPropertyTranslator('foo', 'bar', true));
    }

    public function testTranslate(): void
    {
        $language = new TranslatableExpressionLanguage();
        $language->getVariableMapper()
            ->map('simple', SimpleEntity::class)
            ->map('magic', MagicEntity::class);

        $factory = $this->createMock(PropertyTranslatorFactory::class);
        $factory->expects($this::once())
            ->method('createLookupTranslator')
            ->with('simple', new PropertyMetadata(
                SimpleEntity::class,
                'd',
                [new ClassAttribute('simple')]
            ))
            ->willReturn($propTranslator = new ColumnReferenceTranslator('foo', 'bar'));
        $factory->expects($this::never())
            ->method('createReferenceTranslator');
        $language->addPropertyTranslatorFactory($factory);

        foreach (['foo', 'bar'] as $functionName) {
            $language->addFunction(new TranslatableExpressionFunction(
                $functionName,
                fn (...$args) => "\date('Y-m-d H:i:s')",
                fn ($p, ...$args) => \date('Y-m-d H:i:s'),
                new PassthroughFunctionTranslator($functionName)
            ));
        }

        $this::assertEquals(
            new TranslatedExpression(
                $language->parse('foo(simple.d)', ['simple', 'magic']),
                ['magic'],
                ['simple.d' => $propTranslator],
                ['foo'      => new PassthroughFunctionTranslator('foo')]
            ),
            $language->translate('foo(simple.d)', ['simple', 'magic'], ['magic'])
        );
    }
}
