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

namespace sad_spirit\el2sql\translators;

use sad_spirit\el2sql\{
    PropertyLookupTranslator,
    PropertyMetadata,
    PropertyReferenceTranslator,
    PropertyTranslatorFactory,
    attributes\BaseSubselect,
    attributes\CustomLookup,
    attributes\CustomReference,
    attributes\ScalarSubselect,
    attributes\Parameter,
    attributes\PropertyToColumnMap,
    attributes\Table,
    attributes\TranslatorAttribute,
    exceptions\InvalidArgumentException
};
use sad_spirit\pg_builder\nodes\TypeName;
use sad_spirit\pg_gateway\{
    TableDefinition,
    TableLocator,
    conditions\ForeignKeyCondition,
    metadata\Column,
    metadata\ForeignKey,
    metadata\TableName
};

/**
 * Creates property translators configured by attributes available in the package
 */
final readonly class DefaultPropertyTranslatorFactory implements PropertyTranslatorFactory
{
    public function __construct(protected readonly TableLocator $tableLocator)
    {
    }

    public function createLookupTranslator(string $variableName, PropertyMetadata $property): ?PropertyLookupTranslator
    {
        foreach ($property->propertyAttributes as $attribute) {
            if ($attribute instanceof CustomLookup) {
                return new ($attribute->name)($this->tableLocator, $variableName, $property);

            } elseif ($attribute instanceof BaseSubselect) {
                $base       = $this->getTableDefinition($property->classAttributes, $property->className);
                $related    = $this->tableLocator->getTableDefinition($attribute->table);
                $foreignKey = $this->getForeignKey($base, $related->getName(), $attribute->foreignKey);
                return new properties\SubselectLookupTranslator(
                    $variableName,
                    $this->tableLocator->createGateway($related->getName()),
                    $this->getRelatedColumn($related, $attribute->column),
                    new ForeignKeyCondition($foreignKey, $foreignKey->childTable->equals($base->getName())),
                    $attribute instanceof ScalarSubselect
                );
            }
        }

        foreach ($property->classAttributes as $attribute) {
            if (
                $attribute instanceof PropertyToColumnMap
                && \array_key_exists($property->propertyName, $attribute->forward)
            ) {
                return new properties\ColumnReferenceTranslator(
                    $variableName,
                    $this->getTableDefinition($property->classAttributes, $property->className)
                        ->getColumns()
                        ->get($attribute->forward[$property->propertyName])
                );
            }
        }

        return null;
    }

    public function createReferenceTranslator(
        string $variableName,
        PropertyMetadata $property
    ): ?PropertyReferenceTranslator {
        foreach ($property->propertyAttributes as $attribute) {
            if ($attribute instanceof CustomReference) {
                return new ($attribute->name)($this->tableLocator, $variableName, $property);

            } elseif ($attribute instanceof Parameter) {
                $typeName = null === $attribute->type
                    ? null
                    : $this->tableLocator->getParser()
                        ->parseTypeName($attribute->type);
                return new properties\ParameterTranslator($variableName, $property->propertyName, $typeName);

            } elseif ($attribute instanceof BaseSubselect) {
                if (!$attribute->lazy) {
                    // Assuming the property is loaded, just add a parameter using the property for a value
                    $column = $this->getRelatedColumn(
                        $this->tableLocator->getTableDefinition($attribute->table),
                        $attribute->column
                    );
                    $typeName = $this->tableLocator->getTypeConverterFactory()
                        ->createTypeNameNodeForOID($column->typeOID);
                    if (!$attribute instanceof ScalarSubselect) {
                        $typeName->setBounds([-1]);
                    }
                    return new properties\ParameterTranslator($variableName, $property->propertyName, $typeName);

                } else {
                    $base       = $this->getTableDefinition($property->classAttributes, $property->className);
                    $related    = $this->tableLocator->getTableDefinition($attribute->table);
                    $foreignKey = $this->getForeignKey($base, $related->getName(), $attribute->foreignKey);

                    // We'll use properties corresponding to base table's side of foreign key as parameters
                    $mapped     = $this->mapColumnsToProperties(
                        $property->className,
                        $property->classAttributes,
                        $base,
                        $foreignKey->childTable->equals($base->getName())
                            ? $foreignKey->childColumns
                            : $foreignKey->referencedColumns
                    );
                    return new properties\SubselectReferenceTranslator(
                        $variableName,
                        $this->tableLocator->createGateway($related->getName()),
                        $this->getRelatedColumn($related, $attribute->column),
                        $foreignKey,
                        $mapped,
                        $attribute instanceof ScalarSubselect
                    );
                }
            }
        }

        foreach ($property->classAttributes as $attribute) {
            if (
                $attribute instanceof PropertyToColumnMap
                && \array_key_exists($property->propertyName, $attribute->forward)
            ) {
                $column = $this->getTableDefinition($property->classAttributes, $property->className)
                    ->getColumns()
                    ->get($attribute->forward[$property->propertyName]);
                return new properties\ParameterTranslator(
                    $variableName,
                    $property->propertyName,
                    $this->tableLocator->getTypeConverterFactory()
                        ->createTypeNameNodeForOID($column->typeOID)
                );
            }
        }

        return null;
    }

    /**
     * Returns a TableDefinition object based on Table attribute applied to a class
     *
     * @param array<TranslatorAttribute> $classAttributes
     */
    private function getTableDefinition(array $classAttributes, string $className): TableDefinition
    {
        foreach ($classAttributes as $attribute) {
            if ($attribute instanceof Table) {
                return $this->tableLocator->getTableDefinition($attribute->name);
            }
        }
        throw new InvalidArgumentException(\sprintf(
            "Table attribute is not applied to class '%s', cannot get table definition",
            $className
        ));
    }

    /**
     * Maps columns (of foreign key) to their corresponding properties and types
     *
     * @param TranslatorAttribute[] $classAttributes
     * @param string[] $names
     *
     * @return array<string, array{string, TypeName}>
     */
    private function mapColumnsToProperties(
        string $className,
        array $classAttributes,
        TableDefinition $definition,
        array $names
    ): array {
        foreach ($classAttributes as $attribute) {
            if ($attribute instanceof PropertyToColumnMap) {
                $map     = [];
                $columns = $definition->getColumns();
                foreach ($names as $name) {
                    if (!isset($attribute->reverse[$name])) {
                        throw new InvalidArgumentException(\sprintf(
                            "Class %s does not have a property that maps to column %s of %s.",
                            $className,
                            $name,
                            $definition->getName()
                        ));
                    }
                    $column = $columns->get($name);
                    $map[$name] = [
                        $attribute->reverse[$name],
                        $this->tableLocator->getTypeConverterFactory()
                            ->createTypeNameNodeForOID($column->typeOID)
                    ];
                }
                return $map;
            }
        }
        throw new InvalidArgumentException(\sprintf(
            "Mapping of class %s properties to columns of table %s is not defined",
            $className,
            $definition->getName()
        ));
    }

    /**
     * Returns a foreign key between $base and $related
     *
     * @param string[] $keyColumns If there are several FOREIGN KEY constraints between the tables,
     *                             specifies the columns on the child side that should be part of the key
     */
    private function getForeignKey(TableDefinition $base, TableName $related, array $keyColumns = []): ForeignKey
    {
        $foreignKey = $base->getReferences()
            ->get($related, $keyColumns);
        if ($foreignKey->isRecursive()) {
            throw new InvalidArgumentException(
                "Recursive foreign keys are not supported by Subselect / ScalarSubselect"
            );
        }
        return $foreignKey;
    }

    /**
     * Returns a column to fetch when mapping Subselect / ScalarSubselect
     *
     * If column name is not given explicitly, defaults to a scalar primary key. Throws an exception
     * when either no primary key is defined or a composite one.
     */
    private function getRelatedColumn(TableDefinition $related, ?string $columnName = null): Column
    {
        if (null !== $columnName) {
            return $related->getColumns()->get($columnName);
        }
        switch (\count($primaryKey = $related->getPrimaryKey())) {
            case 0:
                throw new InvalidArgumentException(\sprintf(
                    "Table %s has no primary key, need to specify a column name to fetch",
                    $related->getName()
                ));
            case 1:
                return $primaryKey->getAll()[0];
            default:
                throw new InvalidArgumentException(\sprintf(
                    "Table %s has a composite primary key, need to specify a column name to fetch",
                    $related->getName()
                ));
        }
    }
}
