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

/**
 * Specifies that a property maps to a base table column
 *
 * NB: These attributes will be collected by `ClassMetadata` into a single `PropertyToColumnMap` class attribute, and
 * thus will be absent from `PropertyMetadata`
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Column implements TranslatorAttribute
{
    public function __construct(public ?string $name = null)
    {
    }
}
