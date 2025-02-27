<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\Utils\CodeGen;

use Hyperf\Utils\Exception\InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionParameter;
use ReflectionType;

class PhpParser
{
    public const TYPES = [
        'int',
        'float',
        'string',
        'bool',
        'array',
        'object',
        'resource',
        'mixed',
        'null',
    ];

    protected static ?PhpParser $instance = null;

    protected Parser $parser;

    public function __construct()
    {
        $parserFactory = new ParserFactory();
        $this->parser = $parserFactory->create(ParserFactory::ONLY_PHP7);
    }

    public static function getInstance(): PhpParser
    {
        if (static::$instance) {
            return static::$instance;
        }
        return static::$instance = new static();
    }

    /**
     * @return null|Node\Stmt[]
     */
    public function getNodesFromReflectionClass(ReflectionClass $reflectionClass): ?array
    {
        $code = file_get_contents($reflectionClass->getFileName());
        return $this->parser->parse($code);
    }

    public function getNodeFromReflectionType(ReflectionType $reflection): Node\ComplexType|Node\Identifier|Node\Name
    {
        if ($reflection instanceof \ReflectionUnionType) {
            $unionType = [];
            foreach ($reflection->getTypes() as $objType) {
                $type = $objType->getName();
                if (! in_array($type, static::TYPES)) {
                    $unionType[] = new Node\Name('\\' . $type);
                } else {
                    $unionType[] = new Node\Identifier($type);
                }
            }
            return new Node\UnionType($unionType);
        }

        return $this->getTypeWithNullableOrNot($reflection);
    }

    public function getNodeFromReflectionParameter(ReflectionParameter $parameter): Node\Param
    {
        $result = new Node\Param(
            new Node\Expr\Variable($parameter->getName())
        );

        if ($parameter->isDefaultValueAvailable()) {
            $result->default = $this->getExprFromValue($parameter->getDefaultValue());
        }

        if ($parameter->hasType()) {
            $result->type = $this->getNodeFromReflectionType($parameter->getType());
        }

        if ($parameter->isPassedByReference()) {
            $result->byRef = true;
        }

        if ($parameter->isVariadic()) {
            $result->variadic = true;
        }

        return $result;
    }

    public function getExprFromValue($value): Node\Expr
    {
        return match (gettype($value)) {
            'array' => value(function ($value) {
                $result = [];
                foreach ($value as $item) {
                    $result[] = new Node\Expr\ArrayItem($this->getExprFromValue($item));
                }
                return new Node\Expr\Array_($result, [
                    'kind' => Node\Expr\Array_::KIND_SHORT,
                ]);
            }, $value),
            'string' => new Node\Scalar\String_($value),
            'integer' => new Node\Scalar\LNumber($value),
            'double' => new Node\Scalar\DNumber($value),
            'NULL' => new Node\Expr\ConstFetch(new Node\Name('null')),
            'boolean' => new Node\Expr\ConstFetch(new Node\Name($value ? 'true' : 'false')),
            default => throw new InvalidArgumentException($value . ' is invalid'),
        };
    }

    /**
     * @return Node\Stmt\ClassMethod[]
     */
    public function getAllMethodsFromStmts(array $stmts): array
    {
        $methods = [];
        foreach ($stmts as $namespace) {
            if (! $namespace instanceof Node\Stmt\Namespace_) {
                continue;
            }

            foreach ($namespace->stmts as $class) {
                if (! $class instanceof Node\Stmt\Class_ && ! $class instanceof Node\Stmt\Interface_) {
                    continue;
                }

                foreach ($class->getMethods() as $method) {
                    $methods[] = $method;
                }
            }
        }

        return $methods;
    }

    private function getTypeWithNullableOrNot(ReflectionType $reflection): Node\ComplexType|Node\Identifier|Node\Name
    {
        if (! $reflection instanceof \ReflectionNamedType) {
            throw new \ReflectionException('ReflectionType must be ReflectionNamedType.');
        }

        $name = $reflection->getName();

        if ($reflection->allowsNull() && $name !== 'mixed') {
            return new Node\NullableType($name);
        }

        if (! in_array($name, static::TYPES)) {
            return new Node\Name('\\' . $name);
        }
        return new Node\Identifier($name);
    }
}
