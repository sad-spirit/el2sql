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

namespace sad_spirit\el2sql\translators\properties;

use sad_spirit\el2sql\PropertyReferenceTranslator;
use sad_spirit\pg_builder\nodes\{
    TypeName,
    expressions\NamedParameter,
    expressions\TypecastExpression
};

/**
 * Translates a property access to a named query parameter
 */
final readonly class ParameterTranslator implements PropertyReferenceTranslator
{
    public function __construct(
        private string $tableAlias,
        private string $propertyName,
        private TypeName $typeName,
    ) {
    }

    public function translate(): TypecastExpression
    {
        return new TypecastExpression(new NamedParameter($this->getParameterName()), clone $this->typeName);
    }

    public function getQueryParameters(object $value): array
    {
        return [$this->getParameterName() => $value->{$this->propertyName}];
    }

    private function getParameterName(): string
    {
        return $this->tableAlias . '__' . $this->propertyName;
    }
}
