<?php

namespace Fruit\RouteKit;

use Alom\Graphviz\Digraph;

// This class is only for internal use.
class Node
{
    public $handler;
    private $childNodes;
    private $varChild;
    private $id;

    public function __construct()
    {
        $this->childNodes = array();
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
        foreach ($this->childNodes as $k => $node) {
            if ($path == $k) {
                return array($node, null);
            }
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

    public function compile($argc = 0, $path = '')
    {
        $name = $path;
        if ($name == '') {
            $name = 'root';
        }
        $ret = array();
        if ($this->varChild != null or $this->handler != null) {
            $ret = array(sprintf('case %d: // %s', $this->id, $name));
        }
        $childRet = array();
        if (count($this->childNodes) > 0) {
            foreach ($this->childNodes as $k => $node) {
                $childRet = array_merge($childRet, $node->compile($argc, $path . '/' . $k));
            }
        }

        if ($this->handler != null) {
            $params = array();
            for ($i = 0; $i < $argc; $i++) {
                $params[$i] = '$params[' . $i . ']';
            }
            $ret[] = '    if ($i+1 == $sz) return ' . $this->exportHandler($params, true) . ';';
            if ($this->varChild == null) {
                $ret[] = '    throw new \Exception("no matching rule for url [" . $uri . "]");';
            }
        }
        if ($this->varChild != null) {
            $argc++;
            $ret[] = sprintf('    $state = %d;', $this->varChild->id);
            $ret[] = '    $params[] = $part;';
            $ret[] = '    break;';
            $childRet = array_merge($childRet, $this->varChild->compile($argc, $path . '/[variable]'));
        }

        return array_merge($ret, $childRet);
    }
}
