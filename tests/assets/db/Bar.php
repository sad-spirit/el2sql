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

use sad_spirit\el2sql\attributes\MagicProperty;
use sad_spirit\el2sql\attributes\ScalarSubselect;
use sad_spirit\el2sql\attributes\PropertyToColumnTransformation;
use sad_spirit\el2sql\attributes\Subselect;
use sad_spirit\el2sql\attributes\Table;
use sad_spirit\pg_gateway\metadata\TableName;

#[Table('simple', 'bar')]
#[PropertyToColumnTransformation(snakeCase: true)]
#[MagicProperty('fooName', new ScalarSubselect(new TableName('simple', 'foo'), 'name', lazy: true))]
#[MagicProperty('missingForeignKey', new Subselect(new TableName('simple', 'nopkey')))]
final class Bar
{
    public function __construct(
        public int $id,
        public int $fooId,
        public string $name,
        public string $description
    ) {
    }

    public function __get(string $name)
    {
        return 'some value';
    }
}
