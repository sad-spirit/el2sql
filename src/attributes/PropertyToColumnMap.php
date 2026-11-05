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
 * Specifies a mapping between object properties and base table columns
 *
 * If any column-mapping attributes are added to a class or its properties, `ClassMetadata` will aggregate them
 * into a single `PropertyToColumnMap` attribute, making the original ones unavailable in `PropertyMetadata`.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class PropertyToColumnMap implements TranslatorAttribute
{
    /**
     * Mapping "column name" -> "property name"
     * @var array<string, string>
     */
    public array $reverse;

    public function __construct(
        /**
         * Mapping "property name" -> "column name"
         * @var array<string, string>
         */
        public array $forward
    ) {
        $this->reverse = \array_flip($this->forward);
    }
}
