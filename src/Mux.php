<?php

namespace Fruit\RouteKit;

use Alom\Graphviz\Digraph;

/**
 * Mux is where you place routing rules and dispatch request according to these rules.
 */
class Mux implements Router
{
    private $roots;

    public function __construct()
    {
        $this->roots = array();
    }

    /**
     * Calling right handler/controller according to http method and request uri.
     *
     * @param $methond string of http request method, case insensitive.
     * @param $url string of request uri
     * @return whatever you return in the handler/controller, or an exception if no rule matched.
     */
    public function dispatch($method, $url)
    {
        $method = strtolower($method);
        if (! isset($this->roots[$method])) {
            throw new \Exception('No matching method of ' . $method);
        }
        $cur = $this->roots[$method];
        $arr = explode('/', $url);
        array_shift($arr);
        $params = array();
        $arr_size = count($arr);
        for ($i = 0; $i < $arr_size; $i++) {
            list($cur, $param) = $cur->match($arr[$i]);
            if ($cur == null) {
                throw new \Exception('No Matching handler for ' . $url);
            }
            if ($param != null) {
                $params[] = $param;
            }
        }
        if ($cur->handler == null) {
            throw new \Exception('No Matching handler for ' . $url);
        }

        list($cb, $args) = $cur->handler;

        if (is_array($cb)) {
            $obj = $cb[0];
            if (! is_object($obj)) {
                $ref = new \ReflectionClass($cb[0]);
                if (is_array($args) and count($args) > 0) {
                    $obj = $ref->newInstanceArgs($args);
                } else {
                    $obj = $ref->newInstance();
                }
            }
            $cb[0] = $obj;
        }
        if (count($params) > 0) {
            return call_user_func_array($cb, $params);
        } else {
            return call_user_func($cb);
        }
    }

    private function add($method, $path, $handler, array $constructorArgs = null)
    {
        // initialize root node
        if (!isset($this->roots[$method])) {
            $this->roots[$method] = new Node;
        }
        $root = $this->roots[$method];

        $cur = $root;
        $arr = explode('/', $path);
        array_shift($arr);
        while (count($arr) > 0) {
            $cur_path = array_shift($arr);
            $cur = $cur->register($cur_path);
        }
        if ($cur->handler != null) {
            throw new \Exception('Already registered a handler for ' . $path);
        }
        $cur->handler = array($handler, $constructorArgs);
        return $this;
    }

    public function get($path, $handler, array $constructorArgs = null)
    {
        return $this->add('get', $path, $handler, $constructorArgs);
    }

    public function post($path, $handler, array $constructorArgs = null)
    {
        return $this->add('post', $path, $handler, $constructorArgs);
    }

    public function put($path, $handler, array $constructorArgs = null)
    {
        return $this->add('put', $path, $handler, $constructorArgs);
    }

    public function delete($path, $handler, array $constructorArgs = null)
    {
        return $this->add('delete', $path, $handler, $constructorArgs);
    }

    public function option($path, $handler, array $constructorArgs = null)
    {
        return $this->add('option', $path, $handler, $constructorArgs);
    }

    public function any($path, $handler, array $constructorArgs = null)
    {
        $this->add('get', $path, $handler, $constructorArgs);
        $this->add('post', $path, $handler, $constructorArgs);
        $this->add('put', $path, $handler, $constructorArgs);
        $this->add('delete', $path, $handler, $constructorArgs);
        $this->add('option', $path, $handler, $constructorArgs);
        return $this;
    }

    /**
     * Generate graphviz dot diagram
     */
    public function dot()
    {
        $ret = array();
        foreach ($this->roots as $k => $root) {
            $g = new Digraph($k);
            $root->dot($g);
            $ret[$k] = $g->render();
        }
        return $ret;
    }

    /**
     * Generate static router, convert every dynamic call to handler/controller to static call.
     *
     * This method will generate the defination of a customed class, which implements
     * Fruit\RouteKit\Router, so you can create an instance and use the dispatch() method.
     *
     * @param $clsName string custom class name, default to 'FruitRouteKitGeneratedMux'.
     * @param $indent string how you indent generated class.
     */
    public function compile($clsName = '', $indent = '')
    {
        if ($clsName == '') {
            $clsName = 'FruitRouteKitGeneratedMux';
        }
        $funcs = array();
        $disp = array();
        $ind = $indent . $indent;
        $in3 = $ind . $indent;
        $in4 = $in3 . $indent;
        $stateMap = array();
        $varMap = array();
        $funcMap = array();
        $handlerFuncs = array();
        foreach ($this->roots as $m => $root) {
            $root->fillID(0);
            $stateMap[$m] = $root->stateTable(array());
            $varMap[$m] = $root->varTable(array());
            $funcMap[$m] = $root->funcTable(array());

            // make handlers
            foreach ($funcMap[$m] as $id => $body) {
                $fn = sprintf('handler_%s_%d', $m, $id);
                $handlerFuncs[] = sprintf(
                    'private function %s(array $params){return %s;}' . "\n",
                    $fn,
                    $body
                );
                $funcMap[$m][$id] = $fn;
            }

            // make dispatcher
            $f = $indent . sprintf('private function dispatch%s($uri)', strtoupper($m)) . "\n";
            $f .= $indent . "{\n";
            $f .= $ind . '$arr = explode(\'/\', $uri);' . "\n";
            $f .= $ind . '$arr[] = \'\';' . "\n";
            $f .= $ind . '$state = 0;' . "\n";
            $f .= $ind . '$params = array();' . "\n";
            $f .= $ind . '$sz = count($arr);' . "\n";
            $f .= $ind . 'for ($i = 1; $i < $sz; $i++) {' . "\n";
            $f .= $in3 . '$part = $arr[$i];' . "\n";
            $f .= $in3 . 'if (isset($this->stateMap[' . var_export($m, true) . '][$state][$part])) ' .
                '{$state = $this->stateMap[' . var_export($m, true) .
                '][$state][$part]; continue;}' . "\n";
            $f .= $in3 . 'if ($i+1 == $sz and isset($this->funcMap[' . var_export($m, true) .
                '][$state])) {' . "\n";
            $f .= $in4 . '$f = $this->funcMap[' . var_export($m, true) . '][$state];' . "\n";
            $f .= $in4 . 'return $this->$f($params);' . "\n";
            $f .= $in3 . "}\n";
            $f .= $in3 . 'if (isset($this->varMap[' . var_export($m, true) . '][$state])) ' .
                '{$state = $this->varMap[' . var_export($m, true) .
                '][$state]; $params[] = $part; continue;}' . "\n";
            $f .= $in3 . 'throw new \Exception("no matching rule for url [" . $uri . "]");' . "\n";
            $f .= $ind . "}\n";
            $f .= $ind . 'throw new \Exception(\'No matching rule for \' . $uri);' . "\n";
            $f .= $indent . "}\n";
            $funcs[$m] = $f;
            $disp[] = sprintf('if ($method == %s) {', var_export($m, true));
            $disp[] = sprintf($indent . 'return $this->dispatch%s($uri);', strtoupper($m));
            $disp[] = "}";
        }

        $ret = '<' . "?php\n\n";
        $ret .= 'class ' . $clsName . ' implements Fruit\RouteKit\Router' . "\n";
        $ret .= "{\n";
        $ret .= $indent . 'private $stateMap;' . "\n\n";
        $ret .= $indent . 'private $varMap;' . "\n\n";
        $ret .= $indent . 'private $funcMap;' . "\n\n";
        $ret .= $indent . "public function __construct()\n";
        $ret .= $indent . "{\n";
        $ret .= $ind . '$this->stateMap = ' . var_export($stateMap, true) . ";\n";
        $ret .= $ind . '$this->varMap = ' . var_export($varMap, true) . ";\n";
        $ret .= $ind . '$this->funcMap = ' . var_export($funcMap, true) . ";\n";
        $ret .= $indent . "}\n\n";

        foreach ($funcs as $f) {
            $ret .= $f . "\n";
        }
        $ret .= $indent . 'public function dispatch($method, $uri)' . "\n";
        $ret .= $indent . "{\n";
        $ret .= $ind . '$method = strtolower($method);' . "\n";
        $ret .= $ind . implode("\n" . $ind, $disp) . "\n";
        $ret .= $indent . "}\n";
        $ret .= $indent . implode("\n" . $indent, $handlerFuncs) . "\n";
        $ret .= "}\n\nreturn new $clsName;";
        return $ret;
    }
}
