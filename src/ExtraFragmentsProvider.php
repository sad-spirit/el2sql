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

use sad_spirit\pg_gateway\Fragment;

/**
 * Translators that need to modify query clauses other than WHERE should implement this
 */
interface ExtraFragmentsProvider
{
    /**
     * Extra fragments that should be applied to the query
     *
     * This will usually return instances of
     * {@see \sad_spirit\pg_gateway\fragments\WithClauseFragment WithClauseFragment} or
     * {@see \sad_spirit\pg_gateway\fragments\JoinFragment JoinFragment} adding more tables to the query.
     *
     * @return iterable<Fragment>
     */
    public function getExtraFragments(): iterable;
}
