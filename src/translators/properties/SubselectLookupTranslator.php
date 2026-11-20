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

use sad_spirit\el2sql\PropertyLookupTranslator;
use sad_spirit\pg_builder\{
    Select,
    SelectCommon
};
use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    expressions\LogicalExpression,
    expressions\SubselectExpression
};
use sad_spirit\pg_gateway\{
    builders\ColumnsBuilder,
    TableGateway,
    TableLocator,
    conditions\ForeignKeyCondition,
    metadata\Column,
    walkers\ReplaceTableAliasWalker
};

/**
 * Translates a property access to a subselect joining the base table on the given foreign key
 */
final class SubselectLookupTranslator implements PropertyLookupTranslator
{
    private ?string $alias = null;

    /**
     * Constructor
     *
     * @param string              $variableName Name of variable in the expression, used as an alias for "base" table
     * @param TableGateway        $related      Gateway to the "related" table
     * @param Column              $column       Column to fetch from the "related" table
     * @param ForeignKeyCondition $condition    Used to join the "related" table to the "base" one
     * @param bool                $wrap         Whether to wrap the resultant Select in a SubselectExpression
     */
    public function __construct(
        private readonly string $variableName,
        private readonly TableGateway $related,
        private readonly Column $column,
        private readonly ForeignKeyCondition $condition,
        private readonly bool $wrap = false
    ) {
    }

    public function translate(): ScalarExpression|SelectCommon
    {
        /** @var Select $select */
        $select = $this->related->select(
            (new ColumnsBuilder($this->related->getDefinition()))
                ->only([$this->column->name])
        )
            ->createSelectAST();

        $select->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_SELF, $this->getAlias()));

        $wrapped = new LogicalExpression([$this->condition->generateExpression()]);
        $wrapped->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_JOINED, $this->getAlias()));
        $wrapped->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_SELF, $this->variableName));
        $select->where->and($wrapped[0]);

        return $this->wrap ? new SubselectExpression($select) : $select;
    }

    private function getAlias(): string
    {
        return $this->alias ??= TableLocator::generateAlias();
    }
}
