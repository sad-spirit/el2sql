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

use sad_spirit\el2sql\PropertyReferenceTranslator;
use sad_spirit\el2sql\translators\properties\CustomTranslator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class CustomReference implements TranslatorAttribute
{
    public function __construct(
        /** @var class-string<PropertyReferenceTranslator&CustomTranslator> */
        public string $name,
        /** Class-specific additional configuration */
        public mixed $config = null
    ) {
    }
}
