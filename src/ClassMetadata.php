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

/**
 * Extracts metadata for (public non-static) properties of a class via reflection and provides access to that
 */
readonly class ClassMetadata
{
    public string $className;
    public bool $magic;
    /** @var attributes\TranslatorAttribute[] */
    public array $classAttributes;
    /** @var array<string, array{magic: bool, attributes: attributes\TranslatorAttribute[]}> */
    public array $properties;

    public function __construct(\ReflectionClass $reflection)
    {
        $this->className = $reflection->getName();
        $this->magic     = $reflection->hasMethod('__get')
            && $reflection->getMethod('__get')->isPublic();

        $classAttributes = [];
        $properties      = [];
        $magicProperties = [];
        $nameMap         = null;
        $transformation  = null;

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $properties[$property->getName()] = ['magic' => false, 'attributes' => []];
            foreach (
                $property->getAttributes(
                    attributes\TranslatorAttribute::class,
                    \ReflectionAttribute::IS_INSTANCEOF
                ) as $reflectionAttribute
            ) {
                $attribute = self::instantiateAttribute(
                    $reflectionAttribute,
                    'property ' . $reflection->getName() . '::$' . $property->getName(),
                    $property->getDeclaringClass()->getFileName(),
                    $property->getDeclaringClass()->getStartLine()
                );

                $properties[$property->getName()]['attributes'][] = $attribute;
            }
        }

        foreach (
            $reflection->getAttributes(
                attributes\TranslatorAttribute::class,
                \ReflectionAttribute::IS_INSTANCEOF
            ) as $reflectionAttribute
        ) {
            $attribute = self::instantiateAttribute(
                $reflectionAttribute,
                'class ' . $reflection->getName(),
                $reflection->getFileName(),
                $reflection->getStartLine()
            );

            if ($attribute instanceof attributes\PropertyToColumnMap) {
                $nameMap = $attribute;

            } elseif ($attribute instanceof attributes\PropertyToColumnTransformation) {
                $transformation = $attribute;

            } elseif (!$attribute instanceof attributes\MagicProperty) {
                $classAttributes[] = $attribute;

            } elseif (!$this->magic) {
                throw new exceptions\MappingException(\sprintf(
                    "MagicProperty attribute disallowed without public __get() method for class %s in %s:%d",
                    $reflection->getName(),
                    $reflection->getFileName(),
                    $reflection->getStartLine()
                ));

            } elseif (\array_key_exists($attribute->name, $properties)) {
                throw new exceptions\MappingException(\sprintf(
                    "MagicProperty attribute cannot reference existing property \$%s for class %s in %s:%d",
                    $attribute->name,
                    $reflection->getName(),
                    $reflection->getFileName(),
                    $reflection->getStartLine()
                ));

            } elseif (\array_key_exists($attribute->name, $magicProperties)) {
                $magicProperties[$attribute->name]['attributes'][] = $attribute->attribute;

            } else {
                $magicProperties[$attribute->name] = ['magic' => true, 'attributes' => [$attribute->attribute]];
            }
        }
        $properties            = \array_merge($properties, $magicProperties);
        $nameMap               = $this->createPropertyToColumnMap($nameMap, $transformation, $properties);
        if ([] !== $nameMap->forward) {
            $classAttributes[] = $nameMap;
        }

        $this->classAttributes = $classAttributes;
        $this->properties      = $properties;
    }

    /**
     * Merges all column-related attributes into a single PropertyToColumnMap
     *
     * @param array<string, array{magic: bool, attributes: attributes\TranslatorAttribute[]}> $properties
     */
    private function createPropertyToColumnMap(
        ?attributes\PropertyToColumnMap $explicitMap,
        ?attributes\PropertyToColumnTransformation $transformation,
        array &$properties
    ): attributes\PropertyToColumnMap {
        $hash = $explicitMap?->forward ?? [];
        foreach ($properties as $name => &$property) {
            $column = null;
            foreach ($property['attributes'] as $index => $attribute) {
                if ($attribute instanceof attributes\Column) {
                    $column = $attribute;
                    unset($property['attributes'][$index]);
                    break;
                }
            }

            if (\array_key_exists($name, $hash)) {
                if (null !== $column) {
                    throw new exceptions\MappingException(\sprintf(
                        "Mapping of property %s::%s to column name is ambiguous: specified both "
                        . "via Column property attribute and PropertyToColumnMap class attribute",
                        $this->className,
                        $name
                    ));
                }
                continue;
            }

            if (null !== $column || [] === $property['attributes'] && null !== $transformation) {
                $hash[$name] = $column?->name ?? $this->transformPropertyName($name, $transformation);
            }
        }

        return new attributes\PropertyToColumnMap($hash);
    }

    private function transformPropertyName(
        string $propertyName,
        ?attributes\PropertyToColumnTransformation $transformation
    ): string {
        if (null === $transformation) {
            return $propertyName;
        }
        if ($transformation->snakeCase) {
            $propertyName = \strtolower(\preg_replace(
                ['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'],
                '\1_\2',
                $propertyName
            ) ?? $propertyName);
        }
        return $transformation->addPrefix . $propertyName;
    }

    /**
     * Returns metadata for a given property (can be magic one)
     */
    public function getPropertyMetadata(string $name): PropertyMetadata
    {
        if (!$this->magic && !\array_key_exists($name, $this->properties)) {
            throw new exceptions\OutOfBoundsException(\sprintf(
                "Neither \$%s property nor magic __get() method defined for class %s",
                $name,
                $this->className
            ));
        }

        $property = $this->properties[$name] ?? ['magic' => true, 'attributes' => []];
        return new PropertyMetadata(
            $this->className,
            $name,
            $property['magic'],
            $this->classAttributes,
            $property['attributes']
        );
    }

    /**
     * Wrapper for \ReflectionAttribute::newInstance()
     *
     * @param \ReflectionAttribute<attributes\TranslatorAttribute> $reflection
     */
    private static function instantiateAttribute(
        \ReflectionAttribute $reflection,
        string $target,
        string $file,
        int $line
    ): attributes\TranslatorAttribute {
        try {
            $attribute = $reflection->newInstance();
        } catch (\Error $e) {
            throw new exceptions\MappingException(\sprintf(
                'Invalid attribute %s for %s in %s:%d%s%s',
                $reflection->getName(),
                $target,
                $file,
                $line,
                \PHP_EOL,
                $e->getMessage()
            ));
        }

        assert($attribute instanceof attributes\TranslatorAttribute);
        return $attribute;
    }
}
