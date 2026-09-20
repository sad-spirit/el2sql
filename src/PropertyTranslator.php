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

use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\SelectCommon;

/**
 * Translates a property access to an SQL construct
 *
 * Note that a property will usually have two translators: one for lookup and one for a reference.
 * Given the `foo.prop_one === bar.prop_two` expression, four cases are possible:
 * - Neither `foo` nor `bar` is known (= given in the `$values` array). We generate something like
 *  `foo.column_one = bar.column_two` assuming both `foo` and `bar` need to be looked up.
 * - `foo` is known, `bar` is not. We generate something like `:foo__prop_one = bar.column_two` assuming we need
 *   to look up `bar` using the property of the given `foo`.
 * - `bar` is known, `foo` is not. Similar to above, we generate `foo.column_one = :bar__prop_two`.
 * - Both `foo` and `bar` are known. This case should preferably be resolved on PHP side before generating the query.
 */
interface PropertyTranslator
{
    /**
     * Returns an SQL construct used to represent the property access
     */
    public function translate(): ScalarExpression|SelectCommon;

    /**
     * Returns values for query parameters based on the object containing this property
     *
     * @return array<string, mixed>
     */
    public function getQueryParameters(object $value): array;
}
