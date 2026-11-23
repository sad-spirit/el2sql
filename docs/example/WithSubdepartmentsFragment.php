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

namespace sad_spirit\el2sql\example;

use sad_spirit\pg_builder\{
    Parser,
    Statement,
    nodes\WithClause
};
use sad_spirit\pg_gateway\{
    ParameterHolder,
    TableLocator,
    fragments\WithClauseFragment,
    holders\EmptyParameterHolder
};

class WithSubdepartmentsFragment extends WithClauseFragment
{
    private const SQL = <<<'QRY'
    with recursive subdepartments as (
        select id, array[id] as path, name
        from example.departments
        where parent_id is null
        union all
        select d.id, path || d.id, d.name
        from example.departments as d, subdepartments as sd
        where d.parent_id = sd.id
    )
    QRY;

    public function __construct(private readonly Parser $parser)
    {
        parent::__construct();
    }

    public function getKey(): ?string
    {
        return TableLocator::hash(self::class);
    }

    public function getParameterHolder(): ParameterHolder
    {
        return new EmptyParameterHolder();
    }

    protected function createWithClause(Statement $statement): WithClause
    {
        return $this->parser->parseWithClause(self::SQL);
    }
}
