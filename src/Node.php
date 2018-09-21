<?php

namespace Fruit\RouteKit;

use Alom\Graphviz\Digraph;
use Exception;
use ReflectionClass;
use ReflectionFunction;
use ReflectionParameter;
use Fruit\CompileKit\Block;
use Fruit\CompileKit\Value;
use Fruit\CompileKit\Renderable;
use Fruit\CompileKit\FunctionCall as Call;

// This class is only for internal use.
class Node
{
    private $handler;
    private $parameters;
    private $childNodes;
    private $varChild;
    private $id;
    private $inputFilters;
    private $outputFilters;

    /**
     * Get parameter definition from handler.
     */
    public static function getParamReflections($handler): array
    {
        list($f) = Util::reflectionCallable($handler);
        return $f->getParameters();
    }

    public function __construct()
    {
        $this->childNodes = array();
        $this->inputFilters = array();
        $this->outputFilters = array();
    }

    public function addInputFilter($cb)
    {
        list($f) = Util::reflectionCallable($cb);
        $params = $f->getParameters();
        if (count($params) !== 4) {
            throw new Exception("Input filter must accepts exactly 4 parameters");
        }

        if (!in_array($cb, $this->inputFilters)) {
            $this->inputFilters[] = $cb;
        }
    }

    public function addOutputFilter($cb)
    {
        list($f) = Util::reflectionCallable($cb);
        $params = $f->getParameters();
        if (count($params) !== 1) {
            throw new Exception("Output filter must accepts exactly 1 parameter");
        }

        if (!in_array($cb, $this->outputFilters)) {
            $this->outputFilters[] = $cb;
        }
    }

    public function getFilters(): array
    {
        return array($this->inputFilters, $this->outputFilters);
    }

    public function setHandler(array $handler)
    {
        if ($this->handler !== null) {
            throw new Exception("Handler already exists");
        }
        $this->handler = $handler;
        $this->parameters = self::getParamReflections($handler[0]);
    }

    public function getHandler()
    {
        return $this->handler;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     *
     */
    public function register(string $char): Node
    {
        // test if variable
        if (substr($char, 0, 1) == ':') {
            if ($this->varChild == null) {
                $this->varChild = new Node;
            }
            return $this->varChild;
        }
        if (! isset($this->childNodes[$char])) {
            $this->childNodes[$char] = new Node;
        }
        return $this->childNodes[$char];
    }

    public function match(string $path): array
    {
        // match for static first
        if (isset($this->childNodes[$path])) {
            return array($this->childNodes[$path], null);
        }

        // no match, return variable handler (or null)
        return array($this->varChild, $path);
    }

    public function exportHandler(array $params, bool $raw = false, Interceptor $int = null): Renderable
    {
        $ret = new Block;
        if (!is_array($this->handler)) {
            return $ret;
        }

        list($h, $args) = $this->handler;

        if (is_array($h)) {
            $paramFiller = function (Call $c): Call {
                return $c;
            };
            if (count($params) > 0) {
                $paramFiller = function (Call $c) use ($params, $raw): Call {
                    foreach ($params as $p) {
                        if ($raw) {
                            $c->rawArg($p);
                            continue;
                        }

                        $c->arg($p);
                    }
                    return $c;
                };
            }

            $argFiller = function (Call $c): Call {
                return $c;
            };
            if (is_array($args)) {
                $argFiller = function (Call $c) use ($args): Call {
                    foreach ($args as $a) {
                        $c->arg($a);
                    }
                    return $c;
                };
            }

            $method = $h[1];

            $exec = (new Block)->append(Value::assign(
                Value::as('$ret'),
                $paramFiller(new Call('$obj->' . $method))
            ));

            $intercept = new Block;
            if ($int !== null) {
                $intercept->append(
                    Value::stmt(
                        (new Call('$int->intercept'))
                            ->rawArg('$url')
                            ->rawArg('$obj')
                            ->arg($method)
                    )
                );
            }

            $input = new Block;
            foreach ($this->inputFilters as $f) {
                $input->append(
                    Value::stmt(
                        Value::as('$ret ='),
                        Util::compileCallable(
                            $f,
                            [
                                '$method',
                                '$url',
                                '[$obj,'.Value::of($method)->render().']',
                                '$params'
                            ]
                        )
                    )
                )
                    ->line('if ($ret !== null) {')
                    ->child((new Block)->return(Value::as('$ret;')))
                    ->line('}');
            }
            $output = new Block;
            foreach ($this->outputFilters as $f) {
                $output->append(Value::stmt(
                    Value::as('$ret ='),
                    Util::compileCallable($f, array('$ret'))
                ));
            }
            $post = (new Block)->return(Value::as('$ret'));

            $obj = new Block;
            if (is_object($h[0])) {
                $obj->append(Value::assign(
                    Value::as('$obj'),
                    Value::of($h[0])
                ));
            } else {
                $obj->append(Value::assign(
                    Value::as('$obj'),
                    $argFiller((new Call('new ' . $h[0])))
                ));
            }

            return $ret->append(
                $obj,
                $input,
                $intercept,
                $exec,
                $output,
                $post
            );
        }

        return (new Block)->return(
            Util::compileCallable($h, $params)
        );
    }

    public function fillID(int $id): int
    {
        $this->id = $id;
        foreach ($this->childNodes as $node) {
            $id = $node->fillId($id + 1);
        }
        if ($this->varChild != null) {
            $id = $this->varChild->fillId($id + 1);
        }
        return $id;
    }

    public function stateTable(array $tbl): array
    {
        $ret = array();
        foreach ($this->childNodes as $k => $v) {
            $ret[$k] = $v->id;
            $tbl = $v->stateTable($tbl);
        }
        if ($this->varChild !== null) {
            $tbl = $this->varChild->stateTable($tbl);
        }
        if (count($ret) > 0) {
            $tbl[$this->id] = $ret;
        }
        return $tbl;
    }

    public function varTable(array $tbl): array
    {
        foreach ($this->childNodes as $v) {
            $tbl = $v->varTable($tbl);
        }
        if ($this->varChild != null) {
            $tbl = $this->varChild->varTable($tbl);
            $tbl[$this->id] = $this->varChild->id;
        }
        return $tbl;
    }

    public function funcTable(array $tbl, int $argc = 0, Interceptor $int = null): array
    {
        foreach ($this->childNodes as $v) {
            $tbl = $v->funcTable($tbl, $argc, $int);
        }
        if ($this->varChild !== null) {
            $tbl = $this->varChild->funcTable($tbl, $argc+1, $int);
        }
        if ($this->handler !== null) {
            $params = array();
            for ($i = 0; $i < $argc; $i++) {
                $params[$i] = '$params[' . $i . ']';
            }
            $func = new Block;
            $size = count($this->parameters);
            foreach ($params as $idx => $param) {
                if ($idx >= $size) {
                    break;
                }

                $pRef = $this->parameters[$idx];
                if (!method_exists($pRef, 'hasType')) {
                    continue;
                }
                if (!$pRef->hasType()) {
                    continue;
                }

                $pType = $pRef->getType()->__toString();
                switch ($pType) {
                    case 'int':
                        // @codingStandardsIgnoreStart
                        $func
                            ->line(sprintf('if (is_numeric($params[%d]) and strpos($params[%d], ".") === false) $params[%d] += 0;', $idx, $idx, $idx))
                            ->line(sprintf('else throw new Fruit\RouteKit\TypeMismatchException(%s, "int");', var_export($pRef->getName(), true)));
                        // @codingStandardsIgnoreEnd
                        break;
                    case 'float':
                        // @codingStandardsIgnoreStart
                        $func
                            ->line(sprintf('if (is_numeric($params[%d])) $params[%d] += 0.0;', $idx, $idx))
                            ->line(sprintf('else throw new Fruit\RouteKit\TypeMismatchException(%s, "float");', var_export($pRef->getName(), true)));
                        // @codingStandardsIgnoreEnd
                        break;
                    case 'bool':
                        // @codingStandardsIgnoreStart
                        $func
                            ->line(sprintf('$boolParam = strtolower($params[%d]);', $idx))
                            ->line(sprintf('if ($boolParam == "false" or $boolParam == "null" or $boolParam == "0") $params[%d] = false;', $idx))
                            ->line(sprintf('else $params[%d] = $params[%d] == true;', $idx, $idx));
                        // @codingStandardsIgnoreEnd
                        break;
                    case 'string':
                        break;
                    default:
                        throw new Exception(sprintf(
                            'The type of $%s is %s, which is not supported.',
                            $pRef->getName(),
                            $pType
                        ));
                }
            }
            $func->append($this->exportHandler($params, true, $int));
            $tbl[$this->id] = $func;
        }
        return $tbl;
    }

    public function prepare(string $url, array $params, Interceptor $int = null): array
    {
        $handler = $this->getHandler();
        if ($handler == null) {
            throw new Exception('No Matching handler for ' . $url);
        }

        list($cb, $args) = $handler;

        if (is_array($cb)) {
            $obj = $cb[0];
            if (! is_object($obj)) {
                $ref = new ReflectionClass($cb[0]);
                if (!is_array($args) or count($args) < 1) {
                    $args = array();
                }
                $obj = $ref->newInstanceArgs($args);
            }
            $cb[0] = $obj;
            if ($int !== null) {
                $int->intercept($url, $cb[0], $cb[1]);
            }
        }

        if (count($params) > 0) {
            $params = Type::typeConvert($params, $this->getParameters());
        }
        return [$cb, $params];
    }
}
