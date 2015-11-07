<?php

namespace Fruit\RouteKit;

use Alom\Graphviz\Digraph;
use Exception;
use ReflectionClass;
use ReflectionFunction;
use ReflectionParameter;

// This class is only for internal use.
class Node
{
    private $handler;
    private $parameters;
    private $childNodes;
    private $varChild;
    private $id;

    /**
     * Get parameter definition from handler.
     */
    public static function getParamReflections($handler)
    {
        if (!is_callable($handler, true, $callName)) {
            throw new Exception("Handler is not callable");
        }

        if ($callName == 'Closure::__invoke' or strpos($callName, '::') === false) {
            // functions, will throw exception if function not exist
            $ref = new ReflectionFunction($handler);
            return $ref->getParameters();
        }

        if (!is_array($handler)) {
            // Class::StaticMethod form, just convert it to array form
            $handler = explode('::', $handler);
        }

        
        // [class or object, method] form, throw exception if class not exist or method not found
        $ref = new ReflectionClass($handler[0]);
        $method = $ref->getMethod($handler[1]);
        return $method->getParameters();
    }
    
    public function __construct()
    {
        $this->childNodes = array();
    }

    public function setHandler(array $handler)
    {
        $this->handler = $handler;
        $this->parameters = self::getParamReflections($handler[0]);
    }

    public function getHandler()
    {
        return $this->handler;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     *
     */
    public function register($char)
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

    public function match($path)
    {
        // match for static first
        if (isset($this->childNodes[$path])) {
            return array($this->childNodes[$path], null);
        }

        // no match, return variable handler (or null)
        return array($this->varChild, $path);
    }

    public function exportHandler(array $params, $raw = false)
    {
        if (!is_array($this->handler)) {
            return '';
        }

        $param_str = '';
        if (count($params) > 0) {
            $tmp = array();
            foreach ($params as $k => $p) {
                $tmp[$k] = $raw?$p:var_export($p, true);
            }
            $param_str = implode(', ', $tmp);
        }
        list($h, $args) = $this->handler;

        if (is_array($h)) {
            // (new class($args[0], $args[1]...))->method()
            $arg_str = '';
            if (is_array($args)) {
                $tmp = array();
                foreach ($args as $k => $v) {
                    $tmp[$k] = var_export($v, true);
                }
                $arg_str = implode(', ', $tmp);
            }
            if (is_object($h[0])) {
                $h[0] = var_export($h[0], true);
            }
            return sprintf('(new %s(%s))->%s(%s)', $h[0], $arg_str, $h[1], $param_str);
        }

        return $h . '(' . $param_str . ')';
    }

    public function dot(Digraph $g, $curPath = '')
    {
        $name = $curPath;
        if ($name == '') {
            $name = 'root';
        }
        // first, generate node about ourself
        $opt = array('label' => $name);
        if ($this->handler != null) {
            $opt['label'] .= "\n" . $this->exportHandler(array());
        }
        $g->node($name, $opt);

        // then, generate edges and related nodes
        foreach ($this->childNodes as $k => $node) {
            $path = $k;
            $childPath = $curPath . '/' . $path;
            $g->edge(array($name, $childPath), array('label' => $path));
            $node->dot($g, $childPath);
        }

        // last, generate regexpChild
        if ($this->varChild == null) {
            return;
        }

        $childPath = $curPath . '/[var]';
        $g->edge(array($name, $childPath), array('label' => '[*]'));
        $this->varChild->dot($g, $childPath);
    }

    public function fillID($id)
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

    public function stateTable(array $tbl)
    {
        $ret = array();
        foreach ($this->childNodes as $k => $v) {
            $ret[$k] = $v->id;
            $tbl = $v->stateTable($tbl);
        }
        if ($this->varChild != null) {
            $tbl = $this->varChild->stateTable($tbl);
        }
        $tbl[$this->id] = $ret;
        return $tbl;
    }

    public function varTable(array $tbl)
    {
        foreach ($this->childNodes as $v) {
            $tbl = $v->varTable($tbl);
        }
        if ($this->varChild != null) {
            $tbl = $this->varChild->varTable($tbl);
            if ($this->handler == null) {
                $tbl[$this->id] = $this->varChild->id;
            }
        }
        return $tbl;
    }

    public function funcTable(array $tbl, $argc = 0)
    {
        foreach ($this->childNodes as $v) {
            $tbl = $v->funcTable($tbl, $argc);
        }
        if ($this->varChild != null) {
            $argc++;
            $tbl = $this->varChild->funcTable($tbl, $argc);
        }
        if ($this->handler != null) {
            $params = array();
            for ($i = 0; $i < $argc; $i++) {
                $params[$i] = '$params[' . $i . ']';
            }
            $func = array();
            $size = count($this->parameters);
            foreach ($params as $idx => $param) {
                if ($idx >= $size) {
                    break;
                }

                $pRef = $this->parameters[$idx];
                if (!$pRef->hasType()) {
                    continue;
                }

                $pType = $pRef->getType()->__toString();
                switch ($pType) {
                case 'int':
                    $func[] = sprintf('if (is_numeric($params[%d]) and strpos($params[%d], ".") === false) $params[%d] += 0;', $idx, $idx, $idx);
                    $func[] = sprintf('else throw new Fruit\RouteKit\TypeMismatchException(%s, "int");', var_export($pRef->getName(), true));
                    break;
                case 'float':
                    $func[] = sprintf('if (is_numeric($params[%d])) $params[%d] += 0.0;', $idx, $idx);
                    $func[] = sprintf('else throw new Fruit\RouteKit\TypeMismatchException(%s, "float");', var_export($pRef->getName(), true));
                    break;
                case 'bool':
                    $func[] = sprintf('$boolParam = strtolower($params[%d]);', $idx);
                    $func[] = sprintf('if ($boolParam == "false" or $boolParam == "null" or $boolParam == "0") $params[%d] = false;', $idx);
                    $func[] = sprintf('else $params[%d] = $params[%d] == true;', $idx, $idx);
                    break;
                case 'string':
                    break;
                default:
                    throw new Exception(sprintf('The type of $%s is %s, which is not supported.', $pRef->getName(), $pType));
                }
            }
            $func[] = 'return ' . $this->exportHandler($params, true) . ';';
            $tbl[$this->id] = $func;
        }
        return $tbl;
    }
}
