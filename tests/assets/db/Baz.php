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

namespace sad_spirit\el2sql\tests\assets\db;

use sad_spirit\el2sql\attributes\PropertyToColumnTransformation;
use sad_spirit\el2sql\attributes\Subselect;
use sad_spirit\el2sql\attributes\Table;
use sad_spirit\pg_gateway\metadata\TableName;

#[Table('simple', 'baz')]
#[PropertyToColumnTransformation(snakeCase: true)]
final class Baz
{
    public function __construct(
        public readonly int $barId,
        public readonly string $name,
        #[Subselect(new TableName('simple', 'foo'), 'name', lazy: true)]
        public readonly array $missingFoo
    ) {
    }
}
