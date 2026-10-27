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

use Symfony\Component\ExpressionLanguage\{
    ParsedExpression,
    Node\FunctionNode,
    Node\GetAttrNode,
    Node\NameNode,
    Node\Node
};

/**
 * Finds names of variables / properties / functions used in a ParsedExpression
 */
final class ExpressionNamesFinder
{
    /** @var array<string, array<string, true>> */
    private array $attributeNamesHash = [];
    /** @var array<string, true> */
    private array $functionNamesHash = [];

    public function __construct(ParsedExpression $expression)
    {
        $this->findNamesRecursively($expression->getNodes());
    }

    private function findNamesRecursively(Node $node): void
    {
        if ($node instanceof FunctionNode) {
            $this->functionNamesHash[(string)$node->attributes['name']] = true;
        } elseif (
            $node instanceof GetAttrNode
            && GetAttrNode::PROPERTY_CALL === $node->attributes['type']
            && $node->nodes['node'] instanceof NameNode
        ) {
            /** @psalm-var Node[] $node->nodes */
            $variableName = (string)$node->nodes['node']->attributes['name'];
            $propertyName = (string)$node->nodes['attribute']->attributes['value'];

            $this->attributeNamesHash[$variableName] ??= [];
            $this->attributeNamesHash[$variableName][$propertyName] = true;
        }

        /** @var Node $child */
        foreach ($node->nodes as $child) {
            $this->findNamesRecursively($child);
        }
    }

    /**
     * Returns names of variables used in expression
     *
     * @return string[]
     */
    public function getVariableNames(): array
    {
        return \array_keys($this->attributeNamesHash);
    }

    /**
     * Returns names of attributes used in expression
     *
     * @return array<array{string, string}> First element is variable name, second is property name
     */
    public function getAttributeNames(): array
    {
        $names = [];
        foreach ($this->attributeNamesHash as $variableName => $propertyNames) {
            foreach (\array_keys($propertyNames) as $propertyName) {
                $names[] = [$variableName, $propertyName];
            }
        }
        return $names;
    }

    /**
     * Returns names of functions used in expression
     *
     * @return string[]
     */
    public function getFunctionNames(): array
    {
        return \array_keys($this->functionNamesHash);
    }
}
