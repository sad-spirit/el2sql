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
 * Marks a reference-only property, will be translated by ParameterTranslator
 *
 * Unless another attribute is added to the property, trying to create a lookup translator is expected to fail.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Parameter implements TranslatorAttribute
{
    public function __construct(public ?string $type = null)
    {
    }
}
