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

interface PropertyTranslatorFactory
{
    /**
     * Creates a translator for looking up a value from the database
     *
     * Should return `null` if it cannot create a translator for the given metadata
     */
    public function createLookupTranslator(string $variableName, PropertyMetadata $property): ?PropertyLookupTranslator;

    /**
     * Creates a translator for referencing a known value in the query
     *
     * Should return `null` if it cannot create a translator for the given metadata
     */
    public function createReferenceTranslator(
        string $variableName,
        PropertyMetadata $property
    ): ?PropertyReferenceTranslator;
}
