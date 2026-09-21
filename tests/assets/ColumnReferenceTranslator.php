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

namespace sad_spirit\el2sql\tests\assets;

use sad_spirit\el2sql\{
    ExtraFragmentsProvider,
    PropertyTranslator
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    ScalarExpression
};

/**
 * Translates "a.b" to "a.b"
 */
readonly class ColumnReferenceTranslator implements PropertyTranslator, ExtraFragmentsProvider
{
    public function __construct(
        private string $variableName,
        private string $propertyName,
        private array $extraFragments = []
    ) {
    }

    public function getExtraFragments(): iterable
    {
        return $this->extraFragments;
    }

    public function translate(): ScalarExpression
    {
        return new ColumnReference($this->variableName, $this->propertyName);
    }

    public function getQueryParameters(object $value): array
    {
        return [];
    }
}
