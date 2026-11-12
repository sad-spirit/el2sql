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
    attributes\CustomLookup,
    attributes\CustomReference,
    attributes\PropertyToColumnMap,
    attributes\Table,
    attributes\TranslatorAttribute,
    exceptions\InvalidArgumentException
};
use sad_spirit\pg_gateway\{
    TableDefinition,
    TableLocator
};

/**
 * Creates property translators configured by attributes available in the package
 */
final class DefaultPropertyTranslatorFactory implements PropertyTranslatorFactory
{
    public function __construct(protected readonly TableLocator $tableLocator)
    {
    }

    public function createLookupTranslator(string $variableName, PropertyMetadata $property): ?PropertyLookupTranslator
    {
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
        foreach ($property->propertyAttributes as $attribute) {
            if ($attribute instanceof CustomLookup) {
                return new ($attribute->name)($this->tableLocator, $variableName, $property);
            }
        }

        return null;
    }

    public function createReferenceTranslator(
        string $variableName,
        PropertyMetadata $property
    ): ?PropertyReferenceTranslator {
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
        foreach ($property->propertyAttributes as $attribute) {
            if ($attribute instanceof CustomReference) {
                return new ($attribute->name)($this->tableLocator, $variableName, $property);
            }
        }

        return null;
    }

    /**
     * Returns a TableDefinition object based on Table attribute applied to a class
     *
     * @param array<TranslatorAttribute> $classAttributes
     */
    protected function getTableDefinition(array $classAttributes, string $className): TableDefinition
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
}
