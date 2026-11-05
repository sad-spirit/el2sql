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

use sad_spirit\el2sql\attributes\Column;
use sad_spirit\el2sql\attributes\MagicProperty;
use sad_spirit\el2sql\attributes\PropertyToColumnTransformation;

#[PropertyToColumnTransformation(addPrefix: 'ooh_', snakeCase: true)]
#[MagicProperty('magicFoo', new Column())]
#[MagicProperty('magic_bar', new Column('ooh_bar'))]
class PropertyMapWithPropertyTransformation
{
    public function __construct(
        public string $fooBar,
        #[Column]
        public string $barBaz,
        #[Column('duh_baz')]
        public string $duh,
        #[PropertyAttribute('uh_oh')]
        public string $quux,
    ) {
    }

    public function __get(string $name)
    {
        return 'anything';
    }
}
