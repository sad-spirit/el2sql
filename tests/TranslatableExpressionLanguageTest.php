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

use PHPUnit\Framework\{
    TestCase,
    Attributes\DataProvider
};
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
use sad_spirit\pg_builder\SqlBuilderWalker;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

class TranslatableExpressionLanguageTest extends TestCase
{
    use Psr6Mocks;
    use NormalizeWhitespace;

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
            $translatorOne = new PassthroughFunctionTranslator('date')
        ));
        $language->register(
            'ddatte',
            fn (...$args) => "\date('Y-m-d H:i:s')",
            fn ($p, ...$args) => \date('Y-m-d H:i:s'),
            $translatorTwo = new PassthroughFunctionTranslator('date')
        );

        $this::assertSame($translatorOne, $language->getFunctionTranslator('date'));
        $this::assertSame($translatorTwo, $language->getFunctionTranslator('ddatte'));
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
                false,
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

    #[DataProvider('builtinFunctionsProvider')]
    public function testBuiltinFunctions(string $expression, string $translated): void
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
            ->method('createLookupTranslator')
            ->with('foo', $metadata)
            ->willReturn($lookup = new ColumnReferenceTranslator('foo', 'bar'));

        $language = new TranslatableExpressionLanguage(null, [], [$factory]);
        $language->setVariableMapper($mapper);

        $this::assertStringEqualsStringNormalizingWhitespace(
            $translated,
            $language->translate($expression, ['foo'])
                ->getCondition()
                ->generateExpression()
                ->dispatch(new SqlBuilderWalker())
        );
    }

    public static function builtinFunctionsProvider(): iterable
    {
        yield ['is_null(foo.bar)', 'foo.bar is null'];
        yield ['min(10, foo.bar)', 'least(10, foo.bar)'];
        yield ['max(foo.bar, 10)', 'greatest(foo.bar, 10)'];
    }
}
