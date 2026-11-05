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
use sad_spirit\pg_builder\nodes\ColumnReference;
use sad_spirit\pg_gateway\metadata\Column;

/**
 * Translates a property access to ColumnReference Node
 */
final readonly class ColumnReferenceTranslator implements PropertyLookupTranslator
{
    public function __construct(private string $tableAlias, private Column $column)
    {
    }

    public function translate(): ColumnReference
    {
        return new ColumnReference($this->tableAlias, $this->column->name);
    }
}
