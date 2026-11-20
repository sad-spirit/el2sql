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

namespace sad_spirit\el2sql\attributes;

use sad_spirit\pg_gateway\metadata\TableName;

/**
 * Base class for Subselect / ScalarSubselect attributes that have the same configuration
 */
abstract readonly class BaseSubselect implements TranslatorAttribute
{
    public function __construct(
        public TableName $table,
        /**
         * Column of the related table to fetch
         *
         * Defaults to primary key column
         */
        public ?string $column = null,
        /**
         * Specifies the foreign key to use if several are defined
         *
         * Has the same semantics as `$keyColumns` argument of
         * {@see \sad_spirit\pg_gateway\builders\JoinBuilder::onForeignKey()}
         *
         * @var string[]
         */
        public array $foreignKey = [],
        /**
         * Whether the property is always loaded from the DB
         *
         * If `false`, reference translator will generate a parameter with property as a value, if `true` it
         * will generate a subselect using the available part of a foreign key
         */
        public bool $lazy = false
    ) {
    }
}
