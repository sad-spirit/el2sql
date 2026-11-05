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
    attributes\PropertyToColumnMap,
    exceptions\MappingException,
    exceptions\OutOfBoundsException
};
use sad_spirit\el2sql\tests\assets\{
    ClassAttribute,
    MagicEntity,
    PropertyAttribute,
    PropertyMapWithoutPropertyTransformation,
    PropertyMapWithPropertyTransformation,
    SimpleEntity,
    invalid\AmbiguousColumn,
    invalid\InvalidRepeat,
    invalid\MagicPropertyConflictingWithReal,
    invalid\MagicPropertyWithClassAttribute,
    invalid\MagicPropertyWithoutGet
};

class ClassMetadataTest extends TestCase
{
    public function testSimpleEntity(): void
    {
        $metadata = new ClassMetadata(new \ReflectionClass(SimpleEntity::class));

        $this::assertFalse($metadata->magic);
        $this::assertSame(SimpleEntity::class, $metadata->className);
        $this::assertEquals(
            new PropertyMetadata(
                SimpleEntity::class,
                'b',
                false,
                [new ClassAttribute('simple')],
                [new PropertyAttribute('field_b')]
            ),
            $metadata->getPropertyMetadata('b')
        );
        $this::assertEquals(
            new PropertyMetadata(
                SimpleEntity::class,
                'd',
                false,
                [new ClassAttribute('simple')],
                []
            ),
            $metadata->getPropertyMetadata('d')
        );

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('Neither $c property nor magic __get()');
        $metadata->getPropertyMetadata('c');
    }

    public function testMagicEntity(): void
    {
        $metadata = new ClassMetadata(new \ReflectionClass(MagicEntity::class));

        $this::assertTrue($metadata->magic);
        $this::assertSame(MagicEntity::class, $metadata->className);
        $this::assertEquals(
            new PropertyMetadata(
                MagicEntity::class,
                'foo',
                true,
                [new ClassAttribute('magic')],
                [new PropertyAttribute('magic_foo')]
            ),
            $metadata->getPropertyMetadata('foo')
        );
        $this::assertEquals(
            new PropertyMetadata(
                MagicEntity::class,
                'bar',
                false,
                [new ClassAttribute('magic')],
                [new PropertyAttribute('real_bar')]
            ),
            $metadata->getPropertyMetadata('bar')
        );
        $this::assertEquals(
            new PropertyMetadata(
                MagicEntity::class,
                'baz',
                true,
                [new ClassAttribute('magic')],
                [new PropertyAttribute('magic_baz')]
            ),
            $metadata->getPropertyMetadata('baz')
        );
        $this::assertEquals(
            new PropertyMetadata(
                MagicEntity::class,
                'quux',
                true,
                [new ClassAttribute('magic')]
            ),
            $metadata->getPropertyMetadata('quux')
        );
    }

    public function testGenericAttributeError(): void
    {
        $this::expectException(MappingException::class);
        $this::expectExceptionMessage('must not be repeated');

        new ClassMetadata(new \ReflectionClass(InvalidRepeat::class));
    }

    public function testDisallowMagicPropertyWithoutGet(): void
    {
        $this::expectException(MappingException::class);
        $this::expectExceptionMessage('MagicProperty attribute disallowed without public __get() method');

        new ClassMetadata(new \ReflectionClass(MagicPropertyWithoutGet::class));
    }

    public function testDisallowMagicPropertyForRealOne(): void
    {
        $this::expectException(MappingException::class);
        $this::expectExceptionMessage('MagicProperty attribute cannot reference existing property $foo');

        new ClassMetadata(new \ReflectionClass(MagicPropertyConflictingWithReal::class));
    }

    public function testDisallowMagicPropertyWithoutTargetProperty(): void
    {
        $this::expectException(MappingException::class);
        $this::expectExceptionMessage('MagicProperty attribute accepts only attributes applicable to properties');

        new ClassMetadata(new \ReflectionClass(MagicPropertyWithClassAttribute::class));
    }

    public function testAmbiguousColumnMapping(): void
    {
        $this::expectException(MappingException::class);
        $this::expectExceptionMessage('ambiguous');

        new ClassMetadata(new \ReflectionClass(AmbiguousColumn::class));
    }

    public function testPropertyMapWithoutPropertyTransformation(): void
    {
        $metadata = new ClassMetadata(new \ReflectionClass(PropertyMapWithoutPropertyTransformation::class));
        $this::assertEquals(
            [new PropertyToColumnMap([
                'foo'       => 'col_foo',
                'magic_foo' => 'ooh_foo',
                'bar'       => 'bar',
                'baz'       => 'col_baz',
                'magic_bar' => 'magic_bar',
                'magic_baz' => 'ooh_baz',
            ])],
            $metadata->classAttributes
        );
    }

    public function testPropertyMapWithPropertyTransformation(): void
    {
        $metadata = new ClassMetadata(new \ReflectionClass(PropertyMapWithPropertyTransformation::class));
        $this::assertEquals(
            [new PropertyToColumnMap([
                'fooBar'    => 'ooh_foo_bar',
                'magic_bar' => 'ooh_bar',
                'barBaz'    => 'ooh_bar_baz',
                'duh'       => 'duh_baz',
                'magicFoo'  => 'ooh_magic_foo'
            ])],
            $metadata->classAttributes
        );
    }
}
