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

use sad_spirit\el2sql\attributes\MagicProperty;

#[ClassAttribute('magic')]
#[UnrelatedAttribute]
#[MagicProperty('foo', new PropertyAttribute('magic_foo'))]
#[MagicProperty('baz', new PropertyAttribute('magic_baz'))]
class MagicEntity
{
    public function __construct(
        #[UnrelatedAttribute]
        #[PropertyAttribute('real_bar')]
        public string $bar
    ) {
    }

    public function __get(string $name): mixed
    {
        return 'huh?';
    }
}
