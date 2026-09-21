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

use sad_spirit\el2sql\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\Statement;
use sad_spirit\pg_builder\nodes\QualifiedName;
use sad_spirit\pg_builder\nodes\range\RelationReference;
use sad_spirit\pg_gateway\Fragment;
use sad_spirit\pg_gateway\fragments\CustomFragment;

/**
 * Adds an extra table to the FROM clause
 */
class ExtraTableFragment extends CustomFragment
{
    public function __construct(private readonly string $name, ?string $key, int $priority = Fragment::PRIORITY_DEFAULT)
    {
        parent::__construct($key, $priority);
    }

    public function applyTo(Statement $statement): void
    {
        if (!isset($statement->from)) {
            throw new InvalidArgumentException(\sprintf(
                "Fragment can only be applied to Statements containing a FROM clause, instance of %s given",
                $statement::class
            ));
        }
        $statement->from[] = new RelationReference(new QualifiedName($this->name));
    }
}
