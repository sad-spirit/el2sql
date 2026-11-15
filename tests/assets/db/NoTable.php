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
    CustomReference,
    Parameter
};
use sad_spirit\el2sql\tests\assets\NullReferenceTranslator;

class NoTable
{
    public function __construct(
        public int $id,
        #[Column('my_name')]
        public string $name,
        #[Parameter('integer[]')]
        public array $typed = [],
        #[Parameter()]
        public string $untyped = '',
        #[CustomReference(NullReferenceTranslator::class)]
        public ?string $custom = null
    ) {
    }
}
