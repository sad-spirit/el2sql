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
 * Specifies the name of the table whose columns / foreign keys will be used in translation
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Table implements TranslatorAttribute
{
    public TableName $name;

    public function __construct(string ...$nameParts)
    {
        $this->name = new TableName(...$nameParts);
    }
}
