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

use sad_spirit\el2sql\attributes\TranslatorAttribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ClassAttribute implements TranslatorAttribute
{
    public function __construct(public string $name)
    {
    }
}
