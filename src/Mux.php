<?php

namespace Fruit\RouteKit;

use Alom\Graphviz\Digraph;
use CodeGen\UserClass;
use Exception;
use ReflectionClass;
use ReflectionParameter;

/**
 * Mux is where you place routing rules and dispatch request according to these rules.
 */
class Mux implements Router
{
    private $roots;
    private $interceptor;

    public function __construct()
    {
        $this->roots = array();
    }

    public function setInterceptor(Interceptor $int)
    {
        $this->interceptor = $int;
        return $this;
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
            throw new Exception('No matching method of ' . $method);
        }
        $cur = $this->roots[$method];
        $arr = explode('/', $url);
        array_shift($arr);
        $params = array();
        $arr_size = count($arr);
        for ($i = 0; $i < $arr_size; $i++) {
            list($cur, $param) = $cur->match($arr[$i]);
            if ($cur == null) {
                throw new Exception('No Matching handler for ' . $url);
            }
            if ($param != null) {
                $params[] = $param;
            }
        }
        return $cur->execute($url, $params, $this->interceptor);
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
        if ($cur->getHandler() != null) {
            throw new Exception('Already registered a handler for ' . $path);
        }
        $cur->setHandler(array($handler, $constructorArgs));
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

    private function doCompile($clsName = '', $indent = '    ')
    {
        if ($clsName == '') {
            $clsName = 'Fruit\RouteKit\GeneratedMux';
        }

        $gen = new UserClass($clsName);
        $gen->implementInterface('Fruit\RouteKit\Router');

        $funcs = array();
        $disp = array();
        $in1 = $indent;
        $in2 = $in1 . $in1;
        $stateMap = array();
        $varMap = array();
        $funcMap = array();
        $handlerFuncs = array();
        foreach ($this->roots as $m => $root) {
            $root->fillID(0);
            $stateMap[$m] = $root->stateTable(array());
            $varMap[$m] = $root->varTable(array());
            $funcMap[$m] = $root->funcTable(array(), 0, $this->interceptor);

            // make handlers
            foreach ($funcMap[$m] as $id => $body) {
                $fn = sprintf('handler_%s_%d', $m, $id);
                $gen->addMethod('private', $fn, array('$url', '$params'), $body);
                $funcMap[$m][$id] = $fn;
            }
            $method = var_export($m, true);

        }

        $gen->addPrivateProperty('stateMap', $stateMap);
        $gen->addPrivateProperty('varMap', $varMap);
        $gen->addPrivateProperty('funcMap', $funcMap);

        // make dispatcher
        $f = array();
        $f[] = '$method = strtolower($method);';
        $f[] = 'if (!isset($this->stateMap[$method])) {';
        $f[] = '    throw new Exception(\'unsupported method \' . $method);';
        $f[] = '}';
        $f[] = '$arr = explode(\'/\', $uri);';
        $f[] = '$arr[] = \'\';';
        $f[] = '$state = 0;';
        $f[] = '$params = array();';
        $f[] = '$sz = count($arr);';
        $f[] = 'for ($i = 1; $i < $sz; $i++) {';
        $f[] = $in1 . '$part = $arr[$i];';
        $f[] = $in1 . 'if (isset($this->stateMap[$method][$state][$part])) ' . '{';
        $f[] = $in2 . '$state = $this->stateMap[$method][$state][$part];';
        $f[] = $in2 . 'continue;';
        $f[] = $in1 . '}';
        $f[] = $in1 . 'if ($i+1 == $sz and isset($this->funcMap[$method][$state])) {';
        $f[] = $in2 . '$f = $this->funcMap[$method][$state];';
        $f[] = $in2 . 'return $this->$f($uri, $params);';
        $f[] = $in1 . "}";
        $f[] = $in1 . 'if (isset($this->varMap[$method][$state])) ' . '{';
        $f[] = $in2 . '$state = $this->varMap[$method][$state];';
        $f[] = $in2 . '$params[] = $part;';
        $f[] = $in2 . 'continue;';
        $f[] = $in1 . '}';
        $f[] = $in1 . 'throw new Exception("no matching rule for url [" . $uri . "]");';
        $f[] = "}";
        $f[] = 'throw new Exception(\'No matching rule for \' . $uri);';
        $gen->addMethod('public', 'dispatch', array('$method', '$uri'), $f);
        return $gen;
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
    public function compile($clsName = '', $indent = '    ')
    {
        $gen = $this->doCompile($clsName, $indent);
        return "<?php\n" . $gen->render() . "return new $clsName;\n";
    }
}
