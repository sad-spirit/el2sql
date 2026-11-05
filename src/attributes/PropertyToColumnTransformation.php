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
 * Specifies transformations to apply to property name to generate the column name
 *
 * NB: These transformations will be applied to known property names in `ClassMetadata` and merged into a single
 * `PropertyToColumnMap` attribute. This attribute will not be present in `PropertyMetadata`.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class PropertyToColumnTransformation implements TranslatorAttribute
{
    public function __construct(
        public string $addPrefix = '',
        public bool $snakeCase = false
    ) {
    }
}
