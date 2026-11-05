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
use sad_spirit\el2sql\attributes\PropertyToColumnMap;

#[PropertyToColumnMap(['foo' => 'col_foo', 'magic_foo' => 'ooh_foo'])]
#[MagicProperty('magic_bar', new Column())]
#[MagicProperty('magic_baz', new Column('ooh_baz'))]
class PropertyMapWithoutPropertyTransformation
{
    public function __construct(
        public string $foo,
        #[Column()]
        public string $bar,
        #[Column('col_baz')]
        public string $baz,
        public string $quux,
    ) {
    }

    public function __get(string $name): mixed
    {
        return 'something';
    }
}
