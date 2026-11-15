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

use sad_spirit\el2sql\PropertyReferenceTranslator;
use sad_spirit\el2sql\translators\properties\CustomTranslator;
use sad_spirit\pg_builder\nodes\expressions\NamedParameter;

final class NullReferenceTranslator extends CustomTranslator implements PropertyReferenceTranslator
{
    public function getQueryParameters(object $value): array
    {
        return ['null' => null];
    }

    public function translate(): NamedParameter
    {
        return new NamedParameter('null');
    }
}
