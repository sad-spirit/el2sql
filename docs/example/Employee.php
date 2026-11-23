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

use sad_spirit\el2sql\attributes\{
    CustomLookup,
    CustomReference,
    MagicProperty,
    PropertyToColumnTransformation,
    Table
};

/** @property-read int[] $subordinates */
#[Table('example', 'employees')]
#[PropertyToColumnTransformation(snakeCase: true)]
#[MagicProperty('subordinates', new CustomLookup(SubordinatesLookupTranslator::class))]
#[MagicProperty('subordinates', new CustomReference(SubordinatesReferenceTranslator::class))]
final class Employee
{
    private \Closure $subordinatesLoader;
    private ?array $subordinates;
    public function __construct(
        public readonly int $id,
        public readonly int $departmentId,
        public readonly string $firstName,
        public readonly ?string $lastName,
        public readonly string $position,
        public readonly bool $isManager,
        callable $subordinatesLoader
    ) {
        $this->subordinatesLoader = $subordinatesLoader(...);
    }

    public function __get(string $name)
    {
        if ('subordinates' === $name) {
            return $this->subordinates ??= ($this->subordinatesLoader)($this);
        }
        throw new \InvalidArgumentException("Wrong property '$name'");
    }

    public function __isset(string $name): bool
    {
        if ('subordinates' === $name) {
            return null !== $this->subordinates;
        }
        return false;
    }
}
