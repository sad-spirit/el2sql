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

namespace sad_spirit\el2sql;

/**
 * Metadata of a specific property used to create / configure PropertyTranslator implementations
 */
final readonly class PropertyMetadata
{
    public function __construct(
        public string $className,
        public string $propertyName,
        public bool $isMagic = false,
        /** @var attributes\TranslatorAttribute[] */
        public array $classAttributes = [],
        /** @var attributes\TranslatorAttribute[] */
        public array $propertyAttributes = []
    ) {
    }
}
