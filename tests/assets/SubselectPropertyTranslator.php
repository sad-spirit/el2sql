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

use sad_spirit\el2sql\PropertyLookupTranslator;
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\SelectCommon;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    QualifiedName,
    TargetElement,
    lists\TargetList,
    range\RelationReference
};

/**
 * Translates "a.b" to "select b from a"
 */
readonly class SubselectPropertyTranslator implements PropertyLookupTranslator
{
    public function __construct(private string $variableName, private string $propertyName)
    {
    }

    public function translate(): SelectCommon
    {
        $select = new Select(new TargetList([new TargetElement(new ColumnReference($this->propertyName))]));
        $select->from[] = new RelationReference(new QualifiedName($this->variableName));
        return $select;
    }
}
