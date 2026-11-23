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
    PropertyLookupTranslator
};
use sad_spirit\el2sql\translators\properties\CustomTranslator;
use sad_spirit\pg_builder\{
    SelectCommon,
    nodes\ScalarExpression
};

class SubordinatesLookupTranslator extends CustomTranslator implements ExtraFragmentsProvider, PropertyLookupTranslator
{
    public function translate(): ScalarExpression|SelectCommon
    {
        /** @noinspection SqlResolve */
        return $this->tableLocator->getParser()
            ->parseSelectStatement(\sprintf(
                <<<'QRY'
                select e.id
                from example.employees as e, subdepartments as d
                where %1$s.is_manager and
                    %1$s.id <> e.id and
                    e.department_id = d.id and
                    %1$s.department_id = any(d.path)
                QRY,
                $this->variableName
            ));
    }

    public function getExtraFragments(): iterable
    {
        yield new WithSubdepartmentsFragment($this->tableLocator->getParser());
    }
}
