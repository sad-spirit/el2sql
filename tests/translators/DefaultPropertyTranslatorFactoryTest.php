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

namespace sad_spirit\el2sql\tests\translators;

use sad_spirit\pg_builder\nodes\{
    QualifiedName,
    TypeName
};
use sad_spirit\pg_gateway\{
    TableLocator,
    metadata\Column,
};
use sad_spirit\el2sql\{
    VariableMapper,
    exceptions\InvalidArgumentException,
    tests\DatabaseBackedTestCase
};
use sad_spirit\el2sql\tests\assets\db\{
    Foo,
    NoTable
};
use sad_spirit\el2sql\translators\{
    DefaultPropertyTranslatorFactory,
    properties\ColumnReferenceTranslator,
    properties\ParameterTranslator
};

class DefaultPropertyTranslatorFactoryTest extends DatabaseBackedTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::executeSqlFromFile(self::$connection, 'simple-drop.sql', 'simple-create.sql');
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'simple-drop.sql');
    }

    public function testIgnoresPropertiesWithoutMappedColumns(): void
    {
        $mapper  = (new VariableMapper())
            ->map('notable', NoTable::class)
            ->map('foo', Foo::class);
        $factory = new DefaultPropertyTranslatorFactory(new TableLocator(self::$connection));

        $this::assertNull($factory->createLookupTranslator('notable', $mapper->getPropertyMetadata('notable', 'id')));
        $this::assertNull(
            $factory->createReferenceTranslator('notable', $mapper->getPropertyMetadata('notable', 'id'))
        );

        $this::assertNull($factory->createLookupTranslator('foo', $mapper->getPropertyMetadata('foo', 'added')));
        $this::assertNull($factory->createReferenceTranslator('foo', $mapper->getPropertyMetadata('foo', 'added')));
    }

    public function testRequiresTableAttributeOnClass(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Table attribute is not applied to class');

        $mapper  = (new VariableMapper())->map('error', NoTable::class);
        $factory = new DefaultPropertyTranslatorFactory(new TableLocator(self::$connection));

        $factory->createLookupTranslator('error', $mapper->getPropertyMetadata('error', 'name'));
    }

    public function testTranslatesLookups(): void
    {
        $varMapper = (new VariableMapper())->map('foo', Foo::class);
        $factory   = new DefaultPropertyTranslatorFactory($locator = new TableLocator(self::$connection));
        $oidMapper = $locator->getTypeConverterFactory()
            ->getOIDMapper();

        $this::assertEquals(
            new ColumnReferenceTranslator('foo', new Column('id', false, $oidMapper->findOIDForTypeName('int4'))),
            $factory->createLookupTranslator('foo', $varMapper->getPropertyMetadata('foo', 'id'))
        );
        $this::assertEquals(
            new ColumnReferenceTranslator('foo', new Column('name', false, $oidMapper->findOIDForTypeName('text'))),
            $factory->createLookupTranslator('foo', $varMapper->getPropertyMetadata('foo', 'fooName'))
        );
        $this::assertEquals(
            new ColumnReferenceTranslator('foo', new Column(
                'description',
                true,
                $oidMapper->findOIDForTypeName('text')
            )),
            $factory->createLookupTranslator('foo', $varMapper->getPropertyMetadata('foo', 'desc'))
        );
    }

    public function testTranslatesReferences(): void
    {
        $varMapper = (new VariableMapper())->map('foo', Foo::class);
        $factory   = new DefaultPropertyTranslatorFactory(new TableLocator(self::$connection));

        $this::assertEquals(
            new ParameterTranslator(
                'foo',
                'id',
                new TypeName(new QualifiedName('int4'))
            ),
            $factory->createReferenceTranslator('foo', $varMapper->getPropertyMetadata('foo', 'id'))
        );

        $this::assertEquals(
            new ParameterTranslator(
                'foo',
                'fooName',
                new TypeName(new QualifiedName('text'))
            ),
            $factory->createReferenceTranslator('foo', $varMapper->getPropertyMetadata('foo', 'fooName'))
        );

        $this::assertEquals(
            new ParameterTranslator(
                'foo',
                'desc',
                new TypeName(new QualifiedName('text'))
            ),
            $factory->createReferenceTranslator('foo', $varMapper->getPropertyMetadata('foo', 'desc'))
        );
    }
}
