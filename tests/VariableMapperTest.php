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
    VariableMapper,
    exceptions\InvalidArgumentException,
    exceptions\MappingException
};
use sad_spirit\el2sql\tests\assets\{
    ClassAttribute,
    MagicEntity,
    SimpleEntity
};

class VariableMapperTest extends TestCase
{
    use Psr6Mocks;

    public function testNoMappings(): void
    {
        $mapper = new VariableMapper();

        $this::assertSame([], $mapper->getMappedVariableNames());

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage("Mapping for variable 'foo' does not exist");
        $mapper->getClassMetadata('foo');
    }

    public function testCreateMapping(): void
    {
        $mapper = new VariableMapper();

        $mapper->map('simple', SimpleEntity::class);
        $this::assertEquals(['simple'], $mapper->getMappedVariableNames());
        $this::assertEquals(
            new ClassMetadata(new \ReflectionClass(SimpleEntity::class)),
            $mapper->getClassMetadata('simple')
        );
        $this::assertEquals(
            new PropertyMetadata(
                SimpleEntity::class,
                'd',
                false,
                [new ClassAttribute('simple')]
            ),
            $mapper->getPropertyMetadata('simple', 'd')
        );
    }

    public function testDisallowRepeatedMapping(): void
    {
        $mapper = new VariableMapper();
        $mapper->map('simple', SimpleEntity::class);
        $mapper->map('simple_again', SimpleEntity::class);

        $this::expectException(MappingException::class);
        $this::expectExceptionMessage("Mapping for variable 'simple' already created");
        $mapper->map('simple', SimpleEntity::class);
    }

    public function testStoresMetadataInCache(): void
    {
        $simple = new ClassMetadata(new \ReflectionClass(SimpleEntity::class));
        $mapper = new VariableMapper($this->getMockForCacheMiss($simple));

        $mapper->map('simple', SimpleEntity::class);
    }

    public function testLoadsMetadataFromCache(): void
    {
        // Putting the wrong metadata into cache
        $simple = new ClassMetadata(new \ReflectionClass(SimpleEntity::class));
        $mapper = new VariableMapper($this->getMockForCacheHit($simple));

        $mapper->map('magic', MagicEntity::class);
        $this::assertSame($simple, $mapper->getClassMetadata('magic'));
    }
}
