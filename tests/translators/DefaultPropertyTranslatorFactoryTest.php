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
    conditions\ForeignKeyCondition,
    metadata\Column,
    metadata\TableName
};
use sad_spirit\el2sql\{
    VariableMapper,
    exceptions\InvalidArgumentException,
    tests\DatabaseBackedTestCase
};
use sad_spirit\el2sql\tests\assets\{
    NullLookupTranslator,
    NullReferenceTranslator,
    db\Bar,
    db\Baz,
    db\Foo,
    db\NoTable
};
use sad_spirit\el2sql\translators\{
    DefaultPropertyTranslatorFactory,
    properties\ColumnReferenceTranslator,
    properties\ParameterTranslator,
    properties\SubselectLookupTranslator,
    properties\SubselectReferenceTranslator
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

    public function testIgnoresPropertiesWithoutMappedColumnsOrSpecificAttributes(): void
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

    public function testCreatesLookupTranslatorsForMappedColumns(): void
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

    public function testCreatesReferenceTranslatorsForMappedColumns(): void
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

    public function testCreatesTranslatorsUsingParameterAttribute(): void
    {
        $mapper  = (new VariableMapper())
            ->map('notable', NoTable::class);
        $factory = new DefaultPropertyTranslatorFactory(new TableLocator(self::$connection));

        $this::assertNull(
            $factory->createLookupTranslator('notable', $mapper->getPropertyMetadata('notable', 'typed'))
        );
        $this::assertNull(
            $factory->createLookupTranslator('notable', $mapper->getPropertyMetadata('notable', 'untyped'))
        );

        $typeName = new TypeName(new QualifiedName('pg_catalog', 'int4'));
        $typeName->setBounds([-1]);
        $this::assertEquals(
            new ParameterTranslator('notable', 'typed', $typeName),
            $factory->createReferenceTranslator('notable', $mapper->getPropertyMetadata('notable', 'typed'))
        );
        $this::assertEquals(
            new ParameterTranslator('notable', 'untyped'),
            $factory->createReferenceTranslator('notable', $mapper->getPropertyMetadata('notable', 'untyped'))
        );
    }

    public function testCreatesTranslatorsUsingCustomLookupAttribute(): void
    {
        $mapper  = (new VariableMapper())
            ->map('notable', NoTable::class);
        $factory = new DefaultPropertyTranslatorFactory($locator = new TableLocator(self::$connection));

        $property = $mapper->getPropertyMetadata('notable', 'custom');
        $this::assertNull($factory->createLookupTranslator('notable', $property));
        $this::assertEquals(
            new NullReferenceTranslator($locator, 'notable', $property),
            $factory->createReferenceTranslator('notable', $property)
        );
    }

    public function testCreatesTranslatorsUsingCustomReferenceAttribute(): void
    {
        $mapper  = (new VariableMapper())
            ->map('foo', Foo::class);
        $factory = new DefaultPropertyTranslatorFactory($locator = new TableLocator(self::$connection));

        $property = $mapper->getPropertyMetadata('foo', 'custom');
        $this::assertEquals(
            new NullLookupTranslator($locator, 'foo', $property),
            $factory->createLookupTranslator('foo', $property)
        );
        $this::assertNull($factory->createReferenceTranslator('foo', $property));
    }

    public function testSubselectTranslatorWithInvalidTable(): void
    {
        $mapper  = (new VariableMapper())
            ->map('foo', Foo::class);
        $factory = new DefaultPropertyTranslatorFactory(new TableLocator(self::$connection));

        $this::expectException(\InvalidArgumentException::class);
        $this::expectExceptionMessage('does not exist');

        $property = $mapper->getPropertyMetadata('foo', 'invalidTable');
        $factory->createLookupTranslator('foo', $property);
    }

    public function testSubselectTranslatorWithInvalidColumn(): void
    {
        $mapper  = (new VariableMapper())
            ->map('foo', Foo::class);
        $factory = new DefaultPropertyTranslatorFactory(new TableLocator(self::$connection));

        $this::expectException(\OutOfBoundsException::class);
        $this::expectExceptionMessage('does not exist');

        $property = $mapper->getPropertyMetadata('foo', 'invalidColumn');
        $factory->createLookupTranslator('foo', $property);
    }

    public function testSubselectTranslatorWithDefaultColumnNoPrimaryKey(): void
    {
        $mapper  = (new VariableMapper())
            ->map('foo', Foo::class);
        $factory = new DefaultPropertyTranslatorFactory(new TableLocator(self::$connection));

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('no primary key');

        $property = $mapper->getPropertyMetadata('foo', 'defaultNoPkey');
        $factory->createLookupTranslator('foo', $property);
    }

    public function testSubselectTranslatorWithDefaultColumnCompositePrimaryKey(): void
    {
        $mapper  = (new VariableMapper())
            ->map('foo', Foo::class);
        $factory = new DefaultPropertyTranslatorFactory(new TableLocator(self::$connection));

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('composite primary key');

        $property = $mapper->getPropertyMetadata('foo', 'defaultCompositePkey');
        $factory->createLookupTranslator('foo', $property);
    }

    public function testSubselectTranslatorWithMissingForeignKey(): void
    {
        $mapper  = (new VariableMapper())
            ->map('bar', Bar::class);
        $factory = new DefaultPropertyTranslatorFactory(new TableLocator(self::$connection));

        $this::expectException(\InvalidArgumentException::class);
        $this::expectExceptionMessage('No matching foreign keys');

        $property = $mapper->getPropertyMetadata('bar', 'missingForeignKey');
        $factory->createLookupTranslator('bar', $property);
    }

    public function testSubselectTranslatorWithAmbiguousForeignKey(): void
    {
        $mapper  = (new VariableMapper())
            ->map('foo', Foo::class);
        $factory = new DefaultPropertyTranslatorFactory(new TableLocator(self::$connection));

        $this::expectException(\InvalidArgumentException::class);
        $this::expectExceptionMessage('Several matching foreign keys');

        $property = $mapper->getPropertyMetadata('foo', 'ambiguousForeignKey');
        $factory->createLookupTranslator('foo', $property);
    }

    public function testSubselectTranslatorWithExplicitForeignKey(): void
    {
        $mapper  = (new VariableMapper())
            ->map('foo', Foo::class);
        $factory = new DefaultPropertyTranslatorFactory($locator = new TableLocator(self::$connection));

        $property = $mapper->getPropertyMetadata('foo', 'stuff');
        $gateway  = $locator->createGateway('simple.nopkey');

        $this::assertEquals(
            new SubselectLookupTranslator(
                'foo',
                $gateway,
                $gateway->getDefinition()->getColumns()->get('stuff'),
                new ForeignKeyCondition(
                    $gateway->getDefinition()->getReferences()->get(new TableName('simple', 'foo'), ['foo_id']),
                    false
                ),
                false,
            ),
            $factory->createLookupTranslator('foo', $property)
        );

        $typeName = new TypeName(new QualifiedName('text'));
        $typeName->setBounds([-1]);
        $this::assertEquals(
            new ParameterTranslator('foo', 'stuff', $typeName),
            $factory->createReferenceTranslator('foo', $property)
        );
    }

    public function testSubselectTranslatorWithDefaultColumn(): void
    {
        $mapper  = (new VariableMapper())
            ->map('foo', Foo::class);
        $factory = new DefaultPropertyTranslatorFactory($locator = new TableLocator(self::$connection));

        $property = $mapper->getPropertyMetadata('foo', 'bars');
        $gateway  = $locator->createGateway('simple.bar');

        $this::assertEquals(
            new SubselectLookupTranslator(
                'foo',
                $gateway,
                $gateway->getDefinition()->getColumns()->get('id'),
                new ForeignKeyCondition(
                    $gateway->getDefinition()->getReferences()->get(new TableName('simple', 'foo')),
                    false
                ),
                false,
            ),
            $factory->createLookupTranslator('foo', $property)
        );

        $typeName = new TypeName(new QualifiedName('int4'));
        $typeName->setBounds([-1]);
        $this::assertEquals(
            new ParameterTranslator('foo', 'bars', $typeName),
            $factory->createReferenceTranslator('foo', $property)
        );
    }

    public function testLazySubselectReferenceTranslator(): void
    {
        $mapper  = (new VariableMapper())
            ->map('bar', Bar::class);
        $factory = new DefaultPropertyTranslatorFactory($locator = new TableLocator(self::$connection));

        $property = $mapper->getPropertyMetadata('bar', 'fooName');
        $gateway  = $locator->createGateway('simple.foo');

        $this::assertEquals(
            new SubselectReferenceTranslator(
                'bar',
                $gateway,
                $gateway->getDefinition()->getColumns()->get('name'),
                $gateway->getDefinition()->getReferences()->get(new TableName('simple', 'bar')),
                ['foo_id' => ['fooId', new TypeName(new QualifiedName('int4'))]],
                true
            ),
            $factory->createReferenceTranslator('bar', $property)
        );
    }

    public function testLazySubselectReferenceMissingProperty(): void
    {
        $mapper  = (new VariableMapper())
            ->map('baz', Baz::class);
        $factory = new DefaultPropertyTranslatorFactory(new TableLocator(self::$connection));

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('does not have a property');

        $property = $mapper->getPropertyMetadata('baz', 'missingFoo');
        $factory->createReferenceTranslator('bar', $property);
    }
}
