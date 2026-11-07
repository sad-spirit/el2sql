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

use sad_spirit\pg_builder\{
    Node as SqlNode,
    SelectCommon,
    enums\ArrayComparisonConstruct,
    enums\ConstantName,
    enums\LogicalOperator,
    enums\SubselectConstruct,
    enums\SystemFunctionName,
    nodes\QualifiedName,
    nodes\ScalarExpression,
    nodes\TypeName
};
use sad_spirit\pg_builder\nodes\expressions\{
    ArrayComparisonExpression,
    ArrayExpression,
    CaseExpression,
    Constant,
    FunctionExpression,
    IsDistinctFromExpression,
    KeywordConstant,
    LogicalExpression,
    NotExpression,
    NumericConstant,
    OperatorExpression,
    SubselectExpression,
    SystemFunctionCall,
    TypecastExpression,
    WhenExpression
};
use sad_spirit\pg_builder\nodes\lists\{
    FunctionArgumentList,
    ExpressionList
};
use sad_spirit\pg_gateway\{
    Condition,
    Fragment,
    TableLocator
};
use Symfony\Component\ExpressionLanguage\{
    ParsedExpression,
    Node\ArrayNode,
    Node\BinaryNode,
    Node\ConditionalNode,
    Node\ConstantNode,
    Node\FunctionNode,
    Node\GetAttrNode,
    Node\NameNode,
    Node\Node,
    Node\NullCoalesceNode,
    Node\UnaryNode
};

/**
 * Represents an ExpressionLanguage expression being translated to SQL
 */
readonly class TranslatedExpression implements ExtraFragmentsProvider
{
    public function __construct(
        private ParsedExpression $original,
        /** @var string[] */
        private array $knownValues,
        /** @var array<string, PropertyTranslator> */
        private array $propertyTranslators,
        /** @var array<string, FunctionTranslator> */
        private array $functionTranslators
    ) {
    }

    /**
     * Returns extra Fragments that should be applied to the query
     *
     * The Fragments are provided by function and property translators, not the expression itself
     *
     * @return iterable<Fragment>
     */
    public function getExtraFragments(): iterable
    {
        foreach ([...$this->propertyTranslators, ...$this->functionTranslators] as $translator) {
            if ($translator instanceof ExtraFragmentsProvider) {
                yield from $translator->getExtraFragments();
            }
        }
    }

    /**
     * Returns the Condition based on translated ParsedExpression
     *
     * Actual translation is delayed until `Condition`'s `generateExpression()` method is called;
     * we'd prefer using the cached version of a query, as usual.
     */
    public function getCondition(): Condition
    {
        return new class ($this->translate(...), $this->generateKey($this->knownValues)) extends Condition {
            public function __construct(
                /** @var Closure(): ScalarExpression */
                private readonly \Closure $expressionFactory,
                private readonly string $key
            ) {
            }

            protected function generateExpressionImpl(): ScalarExpression
            {
                return ($this->expressionFactory)();
            }

            public function getKey(): ?string
            {
                return $this->key;
            }
        };
    }

    /**
     * Generates a cache key for the returned Condition
     *
     * @param string[] $names
     */
    private function generateKey(array $names): string
    {
        \sort($names, \SORT_STRING);

        return TableLocator::hash([self::class, (string)$this->original, $names]);
    }

    /**
     * Translates the original ParsedExpression to a part of a WHERE clause
     */
    private function translate(): ScalarExpression
    {
        $this->assertIsScalar($translated = $this->translateNode($this->original->getNodes()));
        return $translated;
    }

    /**
     * Throws an exception if the provided Node does not implement ScalarExpression
     *
     * @psalm-assert ScalarExpression $expression
     */
    private function assertIsScalar(SqlNode $expression): void
    {
        if (!$expression instanceof ScalarExpression) {
            throw new exceptions\UnexpectedValueException(\sprintf(
                "Expected an implementation of ScalarExpression, got %s",
                $expression::class
            ));
        }
    }

    private function translateNode(Node $node): ScalarExpression|SelectCommon
    {
        return match ($node::class) {
            ConstantNode::class => $this->translateConstantNode($node),
            GetAttrNode::class => $this->translateGetAttrNode($node),
            UnaryNode::class => $this->translateUnaryNode($node),
            BinaryNode::class => $this->translateBinaryNode($node),
            ArrayNode::class => $this->translateArrayNode($node),
            FunctionNode::class => $this->translateFunctionNode($node),
            NullCoalesceNode::class => $this->translateNullCoalesceNode($node),
            ConditionalNode::class => $this->translateConditionalNode($node),
            default => throw new exceptions\UnexpectedValueException(\sprintf(
                "Unable to translate node of class '%s'",
                $node::class
            ))
        };
    }

    /**
     * Translates a ConstantNode to SQL representation
     *
     * ExpressionLanguage puts both identifiers and actual constants here. We don't allow the former, have to use
     * Reflection to check the private `$isIdentifier` property
     */
    private function translateConstantNode(ConstantNode $node): Constant
    {
        $reflection = new \ReflectionProperty($node, 'isIdentifier');
        if ($reflection->getValue($node)) {
            throw new exceptions\UnexpectedValueException(\sprintf(
                "Unable to translate a free-standing identifier '%s'",
                (string)$node->attributes['value']
            ));
        }
        return Constant::createFromPHPValue($node->attributes['value']);
    }

    /**
     * Converts a GetAttrNode to SQL representation using PropertyTranslators
     *
     * `GetAttrNode` can represent a lot of things in `ExpressionLanguage` we only handle the case where
     *  - it represents a property access (rather than a method call or array index access)
     *  - it is applied to a `NameNode` (rather than to e.g. parenthesized expression)
     *
     * The returned value is a result of getLookupFragment() / getReferenceFragment() call
     * on an Attribute instance found for that attribute's id
     */
    private function translateGetAttrNode(GetAttrNode $node): ScalarExpression|SelectCommon
    {
        /** @var Node[] $node->nodes */
        if (GetAttrNode::PROPERTY_CALL !== $node->attributes['type']) {
            throw new exceptions\UnexpectedValueException("Cannot translate an array index or method call node");
        } elseif (!$node->nodes['node'] instanceof NameNode) {
            throw new exceptions\UnexpectedValueException("Cannot translate a property call on anything but NameNode");
        }

        $name = (string)$node->nodes['node']->attributes['name']
            . '.'
            . (string)$node->nodes['attribute']->attributes['value'];
        if (!isset($this->propertyTranslators[$name])) {
            throw new exceptions\OutOfBoundsException("Missing property translator for '$name'");
        }

        return $this->propertyTranslators[$name]->translate();
    }

    /**
     * Translates a node representing a prefix operator
     */
    private function translateUnaryNode(UnaryNode $node): ScalarExpression
    {
        /** @var Node[] $node->nodes */
        $this->assertIsScalar($argument = $this->translateNode($node->nodes['node']));
        return match ($node->attributes['operator']) {
            'not', '!' => new NotExpression($argument),
            '-' => new OperatorExpression('-', null, $argument),
            '~' => new OperatorExpression('~', null, $argument),
            default => $argument
        };
    }

    /**
     * Translates a node representing an infix operator
     *
     * For `in` / `not in` we actually allow right operand to be a subselect (`PropertyTranslator` can return these)
     */
    private function translateBinaryNode(BinaryNode $node): ScalarExpression
    {
        /** @var Node[] $node->nodes */
        $left  = $this->translateNode($node->nodes['left']);
        $right = $this->translateNode($node->nodes['right']);

        $this->assertIsScalar($left);
        switch ($node->attributes['operator']) {
            case 'in':
                return new OperatorExpression(
                    '=',
                    $left,
                    $right instanceof SelectCommon
                        ? new SubselectExpression($right, SubselectConstruct::ANY)
                        : new ArrayComparisonExpression(ArrayComparisonConstruct::ANY, $right)
                );

            case 'not in':
                return new OperatorExpression(
                    '<>',
                    $left,
                    $right instanceof SelectCommon
                        ? new SubselectExpression($right, SubselectConstruct::ALL)
                        : new ArrayComparisonExpression(ArrayComparisonConstruct::ALL, $right)
                );

            default:
                $this->assertIsScalar($right);
                return match ($node->attributes['operator']) {
                    'or', '||' => new LogicalExpression([$left, $right], LogicalOperator::OR),
                    'and', '&&' => new LogicalExpression([$left, $right], LogicalOperator::AND),
                    '~' => new OperatorExpression('||', $left, $right),
                    '**' => new OperatorExpression('^', $left, $right),
                    '^' => new OperatorExpression('#', $left, $right),
                    '>', '<', '>=', '<=', '+', '-', '*', '/', '%', '&', '|', '>>', '<<' =>
                        new OperatorExpression($node->attributes['operator'], $left, $right),
                    '===' => new IsDistinctFromExpression($left, $right, true),
                    '!==' => new IsDistinctFromExpression($left, $right, false),
                    '==' => new IsDistinctFromExpression(
                        new TypecastExpression($left, new TypeName(new QualifiedName('text'))),
                        new TypecastExpression($right, new TypeName(new QualifiedName('text'))),
                        true
                    ),
                    '!=' => new IsDistinctFromExpression(
                        new TypecastExpression($left, new TypeName(new QualifiedName('text'))),
                        new TypecastExpression($right, new TypeName(new QualifiedName('text'))),
                        false
                    ),
                    'contains' => new OperatorExpression(
                        '!=',
                        new NumericConstant('0'),
                        new FunctionExpression(new QualifiedName('strpos'), new FunctionArgumentList([
                            $left,
                            $right
                        ]))
                    ),
                    'starts with' => new FunctionExpression(
                        new QualifiedName('starts_with'),
                        new FunctionArgumentList([$left, $right])
                    ),
                    // https://stackoverflow.com/a/79726295
                    '..' => new FunctionExpression(
                        new QualifiedName('array_positions'),
                        new FunctionArgumentList([
                            new FunctionExpression(
                                new QualifiedName('array_fill'),
                                new FunctionArgumentList([
                                    new NumericConstant('0'),
                                    new ArrayExpression([
                                        new OperatorExpression(
                                            '+',
                                            new OperatorExpression(
                                                '-',
                                                $right,
                                                clone $left
                                            ),
                                            new NumericConstant('1')
                                        )
                                    ]),
                                    new ArrayExpression([$left])
                                ])
                            ),
                            new NumericConstant('0')
                        ]),
                    ),
                    default => throw new exceptions\UnexpectedValueException(\sprintf(
                        "Unable to translate binary operator '%s'",
                        (string)$node->attributes['operator']
                    ))
                };
        }
    }

    /**
     * Translates a node representing an array literal
     *
     * Keys are ignored, only values used
     */
    private function translateArrayNode(ArrayNode $node): ArrayExpression
    {
        $reflection = new \ReflectionMethod($node, 'getKeyValuePairs');
        $expression = new ArrayExpression();
        /** @var array{key: Node, value: Node} $pair */
        foreach ($reflection->invoke($node) as $pair) {
            $this->assertIsScalar($value = $this->translateNode($pair['value']));
            $expression[] = $value;
        }
        return $expression;
    }

    /**
     * Converts a FunctionNode to SQL representation using FunctionTranslators
     */
    private function translateFunctionNode(FunctionNode $node): ScalarExpression
    {
        /** @var Node[] $node->nodes */
        $functionName = (string)$node->attributes['name'];
        if (!isset($this->functionTranslators[$functionName])) {
            throw new exceptions\OutOfBoundsException(\sprintf(
                "Missing function translator for '%s'",
                $functionName
            ));
        }
        $arguments = [];
        /** @var Node $argument */
        foreach ($node->nodes['arguments']->nodes as $argument) {
            $arguments[] = $this->translateNode($argument);
        }
        return $this->functionTranslators[$functionName]->translate(...$arguments);
    }

    /**
     * Translates a node representing a null-coalescing operator to COALESCE() construct
     */
    private function translateNullCoalesceNode(NullCoalesceNode $node): SystemFunctionCall
    {
        /** @var Node[] $node->nodes */
        $this->assertIsScalar($exprOne = $this->translateNode($node->nodes['expr1']));
        $this->assertIsScalar($exprTwo = $this->translateNode($node->nodes['expr2']));

        return new SystemFunctionCall(SystemFunctionName::COALESCE, new ExpressionList([$exprOne, $exprTwo]));
    }

    /**
     * Translates a ternary operator node to CASE expression
     */
    private function translateConditionalNode(ConditionalNode $node): CaseExpression
    {
        /** @var Node[] $node->nodes */
        $this->assertIsScalar($exprOne = $this->translateNode($node->nodes['expr1']));
        $this->assertIsScalar($exprTwo = $this->translateNode($node->nodes['expr2']));
        $this->assertIsScalar($exprThree = $this->translateNode($node->nodes['expr3']));

        return new CaseExpression(
            [new WhenExpression(new KeywordConstant(ConstantName::TRUE), $exprTwo)],
            $exprThree,
            $exprOne,
        );
    }
}
