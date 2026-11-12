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

use sad_spirit\el2sql\PropertyMetadata;
use sad_spirit\el2sql\PropertyTranslator;
use sad_spirit\pg_gateway\TableLocator;

/**
 * Base class for completely custom property translators
 *
 * Fixes the constructor signature so that `DefaultPropertyTranslatorFactory` is able to instantiate its subclasses
 */
abstract class CustomTranslator implements PropertyTranslator
{
    final public function __construct(
        protected readonly TableLocator $tableLocator,
        protected readonly string $variableName,
        protected readonly PropertyMetadata $property
    ) {
    }
}
