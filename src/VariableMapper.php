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

namespace sad_spirit\el2sql;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as PsrException;

/**
 * Maps variables used in expressions to class metadata
 */
class VariableMapper
{
    /** @var array<string, ClassMetadata> */
    private array $variables = [];

    public function __construct(private readonly ?CacheItemPoolInterface $cache = null)
    {
    }

    /**
     * Creates a mapping for a variable name
     *
     * This accepts a class / object that will be used for that variable in expressions. The ClassMetadata is either
     * created via reflection or loaded from cache if one was provided
     *
     * @return $this
     */
    public function map(string $variableName, object|string $objectOrClass): self
    {
        if (\is_string($objectOrClass) && !\class_exists($objectOrClass)) {
            throw new exceptions\MappingException(
                \sprintf("Class '%s' does not exist", $objectOrClass)
            );
        } elseif (isset($this->variables[$variableName])) {
            throw new exceptions\MappingException(
                \sprintf("Mapping for variable '%s' already created", $variableName)
            );
        }

        $cacheItem = null;
        if (null !== $this->cache) {
            try {
                $cacheItem = $this->cache->getItem(\rawurlencode(
                    'el2sql|' . (\is_string($objectOrClass) ? $objectOrClass : $objectOrClass::class)
                    . '|' . $variableName
                ));
                if ($cacheItem->isHit()) {
                    /** @var ClassMetadata $mapped */
                    $mapped = $cacheItem->get();
                    $this->variables[$variableName] = $mapped;
                    return $this;
                }
            } catch (PsrException) {
            }
        }

        $this->variables[$variableName] = new ClassMetadata(new \ReflectionClass($objectOrClass));

        if (null !== $this->cache && null !== $cacheItem) {
            $this->cache->save($cacheItem->set($this->variables[$variableName]));
        }

        return $this;
    }

    /**
     * Returns metadata for a class mapped to the given variable name
     */
    public function getClassMetadata(string $variableName): ClassMetadata
    {
        return $this->variables[$variableName]
            ?? throw new exceptions\InvalidArgumentException(\sprintf(
                "Mapping for variable '%s' does not exist",
                $variableName
            ));
    }

    /**
     * Returns metadata for a property of a class mapped to the given variable name
     */
    public function getPropertyMetadata(string $variableName, string $propertyName): PropertyMetadata
    {
        return $this->getClassMetadata($variableName)
            ->getPropertyMetadata($propertyName);
    }

    /**
     * Returns all variable names that were mapped
     *
     * Generally, only these variable names should be allowed in expressions that will be translated to SQL
     *
     * @return string[]
     */
    public function getMappedVariableNames(): array
    {
        return \array_keys($this->variables);
    }
}
