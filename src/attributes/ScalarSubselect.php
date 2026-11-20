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
 * Translates a property access to a subselect that returns exactly one row
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class ScalarSubselect extends BaseSubselect
{
}
