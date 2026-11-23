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

namespace sad_spirit\el2sql\example;

use sad_spirit\el2sql\attributes\{
    PropertyToColumnTransformation,
    Subselect,
    Table
};
use sad_spirit\pg_gateway\metadata\TableName;

#[Table('example', 'documents')]
#[PropertyToColumnTransformation(snakeCase: true)]
final readonly class Document
{
    public function __construct(
        public int $id,
        public int $authorId,
        public string $title,
        public string $content,
        /** @var int[] */
        #[Subselect(new TableName('example', 'approval_participants'), 'employee_id')]
        public array $approvalParticipants
    ) {
    }
}
