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

use sad_spirit\el2sql\attributes\{
    Column,
    CustomLookup,
    Subselect,
    Table
};
use sad_spirit\el2sql\tests\assets\NullLookupTranslator;
use sad_spirit\pg_gateway\metadata\TableName;

#[Table('simple', 'foo')]
final readonly class Foo
{
    public function __construct(
        #[Column]
        public int $id,
        public \DateTimeImmutable $added,
        #[Column('name')]
        public string $fooName,
        #[Column('description')]
        public ?string $desc,
        #[CustomLookup(NullLookupTranslator::class)]
        public ?string $custom,
        #[Subselect(new TableName('simple', 'bar'))]
        public array $bars,
        #[Subselect(new TableName('simple', 'nopkey'), 'stuff', ['foo_id'])]
        public array $stuff,
        #[Subselect(new TableName('simple', 'missing'))]
        public array $invalidTable,
        #[Subselect(new TableName('simple', 'bar'), 'missing')]
        public array $invalidColumn,
        #[Subselect(new TableName('simple', 'nopkey'), foreignKey: ['foo_id'])]
        public array $defaultNoPkey,
        #[Subselect(new TableName('simple', 'baz'))]
        public array $defaultCompositePkey,
        #[Subselect(new TableName('simple', 'nopkey'), 'stuff')]
        public array $ambiguousForeignKey
    ) {
    }
}
