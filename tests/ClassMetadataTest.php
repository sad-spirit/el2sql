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
    exceptions\MappingException,
    exceptions\OutOfBoundsException
};
use sad_spirit\el2sql\tests\assets\{
    ClassAttribute,
    MagicEntity,
    PropertyAttribute,
    SimpleEntity,
    invalid\InvalidRepeat,
    invalid\MagicPropertyConflictingWithReal,
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
                [new ClassAttribute('simple')],
                [new PropertyAttribute('field_b')]
            ),
            $metadata->getPropertyMetadata('b')
        );
        $this::assertEquals(
            new PropertyMetadata(
                SimpleEntity::class,
                'd',
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
                [new ClassAttribute('magic')],
                [new PropertyAttribute('magic_foo')]
            ),
            $metadata->getPropertyMetadata('foo')
        );
        $this::assertEquals(
            new PropertyMetadata(
                MagicEntity::class,
                'bar',
                [new ClassAttribute('magic')],
                [new PropertyAttribute('real_bar')]
            ),
            $metadata->getPropertyMetadata('bar')
        );
        $this::assertEquals(
            new PropertyMetadata(
                MagicEntity::class,
                'baz',
                [new ClassAttribute('magic')],
                [new PropertyAttribute('magic_baz')]
            ),
            $metadata->getPropertyMetadata('baz')
        );
        $this::assertEquals(
            new PropertyMetadata(
                MagicEntity::class,
                'quux',
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
}
