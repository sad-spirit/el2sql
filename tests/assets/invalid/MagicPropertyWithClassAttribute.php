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

namespace sad_spirit\el2sql\tests\assets\invalid;

use sad_spirit\el2sql\attributes\MagicProperty;
use sad_spirit\el2sql\tests\assets\ClassAttribute;

#[MagicProperty('foo', new ClassAttribute('bar'))]
class MagicPropertyWithClassAttribute
{
    public function __get(string $name): mixed
    {
        return 'supposedly bar value';
    }
}
