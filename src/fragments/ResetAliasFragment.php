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

namespace sad_spirit\el2sql\fragments;

use sad_spirit\pg_builder\Statement;
use sad_spirit\pg_gateway\{
    TableGateway,
    fragments\CustomFragment,
    walkers\ReplaceTableAliasWalker
};

/**
 * Resets a custom table alias back to the default 'self' used by pg_gateway
 *
 * This fragment should be applied last, hence the priority
 */
final class ResetAliasFragment extends CustomFragment
{
    public function __construct(private readonly string $customAlias)
    {
        parent::__construct('reset-' . $this->customAlias, \PHP_INT_MIN);
    }

    public function applyTo(Statement $statement): void
    {
        $statement->dispatch(new ReplaceTableAliasWalker($this->customAlias, TableGateway::ALIAS_SELF));
    }
}
