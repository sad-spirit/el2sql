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

use sad_spirit\el2sql\{
    ExtraFragmentsProvider,
    PropertyReferenceTranslator
};
use sad_spirit\el2sql\translators\properties\CustomTranslator;
use sad_spirit\pg_builder\{
    SelectCommon,
    nodes\ScalarExpression
};
use sad_spirit\pg_gateway\{
    FragmentList,
    TableSelect
};

class SubordinatesReferenceTranslator extends CustomTranslator implements
    ExtraFragmentsProvider,
    PropertyReferenceTranslator
{
    public function translate(): ScalarExpression|SelectCommon
    {
        // A better approach would be to move is_manager check and department_id param
        // to the non-recursive part of common table expression
        /** @noinspection SqlResolve */
        return $this->tableLocator->getParser()
            ->parseSelectStatement(\sprintf(
                <<<'QRY'
                select e.id
                from example.employees as e, subdepartments as d
                where :%1$s__isManager and
                    :%1$s__id <> e.id and
                    e.department_id = d.id and
                    :%1$s__departmentId = any(d.path)
                QRY,
                $this->variableName
            ));
    }

    public function getExtraFragments(): iterable
    {
        yield new WithSubdepartmentsFragment($this->tableLocator->getParser());
    }

    public function getQueryParameters(object $value): array
    {
        /** @var Employee $value */
        return [
            $this->variableName . '__id'           => $value->id,
            $this->variableName . '__departmentId' => $value->departmentId,
            $this->variableName . '__isManager'    => $value->isManager,
        ];
    }

    /**
     * We reuse this translator to lazy-load the $subordinates property of Employee
     *
     * @return int[]
     */
    public function __invoke(Employee $employee): array
    {
        // Building the query via TableSelect allows caching the generated SQL
        $select = new TableSelect(
            $this->tableLocator,
            $this->tableLocator->createGateway('example.employees'),
            FragmentList::normalize($this->getExtraFragments()),
            $this->translate(...)
        );
        return $select->createSelectStatement()
            ->executeParams($this->tableLocator->getConnection(), $this->getQueryParameters($employee))
            ->fetchColumn('id');
    }
}
