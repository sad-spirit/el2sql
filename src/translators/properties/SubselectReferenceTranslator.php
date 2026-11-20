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

namespace sad_spirit\el2sql\translators\properties;

use sad_spirit\el2sql\PropertyReferenceTranslator;
use sad_spirit\pg_builder\{
    Select,
    SelectCommon,
    enums\LogicalOperator
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    ScalarExpression,
    TypeName,
    expressions\LogicalExpression,
    expressions\NamedParameter,
    expressions\OperatorExpression,
    expressions\SubselectExpression,
    expressions\TypecastExpression,
};
use sad_spirit\pg_gateway\{
    TableGateway,
    TableLocator,
    builders\ColumnsBuilder,
    metadata\Column,
    metadata\ForeignKey,
    walkers\ReplaceTableAliasWalker
};

/**
 * Translates a property access to a subselect using the given foreign key for parameters
 */
class SubselectReferenceTranslator implements PropertyReferenceTranslator
{
    private ?string $alias = null;

    /**
     * Constructor
     *
     * @param string                                 $variableName Name of variable in the expression, used for
     *                                                             parameter names
     * @param TableGateway                           $related      Gateway to the "related" table
     * @param Column                                 $column       Column to fetch from the "related" table
     * @param ForeignKey                             $foreignKey   Used to generate `related.column = :param` conditions
     * @param array<string, array{string, TypeName}> $columnMap    Mapping of "base" table columns to property names
     *                                                             and database types
     * @param bool                                   $wrap         Whether to wrap the resultant Select
     *                                                             in a SubselectExpression
     */
    public function __construct(
        private readonly string $variableName,
        private readonly TableGateway $related,
        private readonly Column $column,
        private readonly ForeignKey $foreignKey,
        private readonly array $columnMap,
        private readonly bool $wrap = false
    ) {
    }

    public function translate(): ScalarExpression|SelectCommon
    {
        $fromChild  = $this->foreignKey->childTable->equals($this->related->getDefinition()->getName());
        $expression = [];
        /** @var Select $select */
        $select     = $this->related->select(
            (new ColumnsBuilder($this->related->getDefinition()))
                ->only([$this->column->name])
        )
            ->createSelectAST();

        foreach ($this->foreignKey as $childColumn => $referencedColumn) {
            $columnName        = $fromChild ? $childColumn : $referencedColumn;
            [$propName, $type] = $this->columnMap[$fromChild ? $referencedColumn : $childColumn];

            $expression[] = new OperatorExpression(
                '=',
                new ColumnReference(TableGateway::ALIAS_SELF, $columnName),
                new TypecastExpression(
                    new NamedParameter($this->variableName . '__' . $propName),
                    clone $type
                )
            );
        }

        $select->where->and(new LogicalExpression($expression, LogicalOperator::AND));
        $select->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_SELF, $this->getAlias()));

        return $this->wrap ? new SubselectExpression($select) : $select;
    }

    public function getQueryParameters(object $value): array
    {
        $parameters = [];
        foreach ($this->columnMap as [$propName,]) {
            /** @psalm-suppress MixedAssignment */
            $parameters[$this->variableName . '__' . $propName] = $value->{$propName};
        }
        return $parameters;
    }

    private function getAlias(): string
    {
        return $this->alias ??= TableLocator::generateAlias();
    }
}
