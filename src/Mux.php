<?php

namespace Fruit\RouteKit;

use Alom\Graphviz\Digraph;
use Exception;
use ReflectionClass;
use ReflectionParameter;
use Fruit\CompileKit\AnonymousClass as C;
use Fruit\CompileKit\FunctionCall as Call;
use Fruit\CompileKit\Value;
use Fruit\CompileKit\Block;
use Fruit\CompileKit\Compilable;
use Fruit\CompileKit\Renderable;

/**
 * Mux is where you place routing rules and dispatch request according to these rules.
 */
class Mux implements Router, Compilable
{
    private $roots;
    private $interceptor;
    private $currentFilters;

    public function __construct()
    {
        $this->roots = array();
        $this->currentFilters = array(array(), array());
    }

    /**
     * Set input and output filters
     *
     * Input filter is nothing but a callable, which accepts 4 parameters:
     * http method, uri, handler callback and handler parameters. Returning
     * anything other than NULL will keep handler from executing, and showing
     * the result to user.
     *
     * Input filter is mean to filter the work flow, so it SHOULD NOT modify
     * these parameters.
     *
     * Output filter is mean to filter data. It is a simple function accepts
     * the result of handler, manipulate on it, then return it back. Anything
     * returned from it will show to user, including NULL.
     */
    public function setFilters(array $input = array(), array $output = array())
    {
        // validate input filters
        foreach ($input as $i) {
            list($f) = Util::reflectionCallable($i);
            if (count($f->getParameters()) !== 4) {
                throw new Exception('Input filter must accepts exactly 4 parameters');
            }
        }
        // validate output filters
        foreach ($output as $o) {
            list($f) = Util::reflectionCallable($o);
            if (count($f->getParameters()) !== 1) {
                throw new Exception('Output filter must accepts exactly 1 parameter');
            }
        }
        $this->currentFilters = array($input, $output);

        return $this;
    }

    public function getFilters()
    {
        return $this->currentFilters;
    }

    /**
     * Set interceptor function.
     *
     * Interceptor is a function running right before calling
     * class-based controller. It MUST accepts exactly three
     * parameters: matched url, class name (for static controller)
     * or object (for method controller), and method name.
     *
     * Interceptor is designed to do change internal state of controller
     * before running it, like injecting deps. It's not the entry point
     * of middleware.
     */
    public function setInterceptor(Interceptor $int)
    {
        $this->interceptor = $int;
        return $this;
    }

    public function getInterceptor()
    {
        return $this->interceptor;
    }

    /**
     * Calling right handler/controller according to http method and request uri.
     *
     * @param $method string of http request method, case insensitive.
     * @param $url string of request uri
     * @return whatever you return in the handler/controller, or an exception if no rule matched.
     */
    public function dispatch(string $method, string $url)
    {
        $method = strtolower($method);
        if (! isset($this->roots[$method])) {
            throw new Exception('No matching method of ' . $method);
        }
        $cur = $this->roots[$method];
        $arr = explode('/', $url);
        array_shift($arr);
        $params = array();
        $arrSize = count($arr);
        for ($i = 0; $i < $arrSize; $i++) {
            list($cur, $param) = $cur->match($arr[$i]);
            if ($cur == null) {
                throw new Exception('No Matching handler for ' . $url);
            }
            if ($param != null) {
                $params[] = $param;
            }
        }
        list($cb, $params) = $cur->prepare($url, $params, $this->interceptor);
        list($i, $o) = $cur->getFilters();
        foreach ($i as $filter) {
            $ret = $filter($method, $url, $cb, $params);
            if ($ret !== null) {
                return $ret;
            }
        }

        $ret = call_user_func_array($cb, $params);

        foreach ($o as $filter) {
            $ret = $filter($ret);
        }
        return $ret;
    }

    private function add(string $method, string $path, $handler, array $constructorArgs = null)
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
            $curPath = array_shift($arr);
            $cur = $cur->register($curPath);
        }
        $old = $cur->getHandler();
        $h = [$handler, $constructorArgs];
        if ($old !== null and $old !== $h) {
            throw new Exception('Already registered a handler for ' . $path);
        } else {
            $cur->setHandler(array($handler, $constructorArgs));
        }

        // add filters
        list($input, $output) = $this->currentFilters;
        foreach ($input as $i) {
            $cur->addInputFilter($i);
        }
        foreach ($output as $o) {
            $cur->addOutputFilter($o);
        }

        return $this;
    }

    public function get(string $path, $handler, array $constructorArgs = null)
    {
        return $this->add('get', $path, $handler, $constructorArgs);
    }

    public function post(string $path, $handler, array $constructorArgs = null)
    {
        return $this->add('post', $path, $handler, $constructorArgs);
    }

    public function put(string $path, $handler, array $constructorArgs = null)
    {
        return $this->add('put', $path, $handler, $constructorArgs);
    }

    public function delete(string $path, $handler, array $constructorArgs = null)
    {
        return $this->add('delete', $path, $handler, $constructorArgs);
    }

    public function option(string $path, $handler, array $constructorArgs = null)
    {
        return $this->add('option', $path, $handler, $constructorArgs);
    }

    public function any(string $path, $handler, array $constructorArgs = null)
    {
        $this->add('get', $path, $handler, $constructorArgs);
        $this->add('post', $path, $handler, $constructorArgs);
        $this->add('put', $path, $handler, $constructorArgs);
        $this->add('delete', $path, $handler, $constructorArgs);
        $this->add('option', $path, $handler, $constructorArgs);
        return $this;
    }

    private function doCompile(): Renderable
    {
        $ret = (new C)
            ->implements('\Fruit\RouteKit\Router');

        $stateMap = array();
        $varMap = array();
        $funcMap = array();
        $funcCnt = 0;
        foreach ($this->roots as $m => $root) {
            $root->fillID(0);
            $stateMap[$m] = $root->stateTable(array());
            $varMap[$m] = $root->varTable(array());
            $funcMap[$m] = $root->funcTable(array(), 0, $this->interceptor);

            // make handlers
            array_walk($funcMap[$m], function (&$body, $id) use ($ret, &$funcCnt) {
                $fn = sprintf('f%d', $funcCnt++);
                $f = $ret->canStatic($fn, 'private');
                $f->accept('method')->type('string');
                $f->accept('url')->type('string');
                $f->accept('params')->type('array');
                $f->accept('int')
                    ->type('\Fruit\RouteKit\Interceptor')
                    ->bindDefault(null);
                $f->append($body);
                $body = $fn;
            });
        }

        $ret->hasStatic('stateMap', 'private')->bindDefault($stateMap);
        $ret->hasStatic('varMap', 'private')->bindDefault($varMap);
        $ret->hasStatic('funcMap', 'private')->bindDefault($funcMap);
        $ret->has('interceptor', 'private');

        // make dispatcher
        $body = (new Block)
            ->stmt(
                Value::as('list($f, $params) ='),
                (new Call('\Fruit\RouteKit\Mux::findRoute'))
                    ->rawArg('$method')
                    ->rawArg('$uri')
                    ->rawArg('self::$stateMap')
                    ->rawArg('self::$varMap')
                    ->rawArg('self::$funcMap')
            )
            ->space()
            ->line('if ($f === null) {')
            ->child(Value::as('throw new \Exception(\'No route for \' . $uri);'))
            ->line('}')
            ->space()
            ->return(
                (new Call('self::$f'))
                ->rawArg('$method')
                ->rawArg('$uri')
                ->rawArg('$params')
                ->rawArg('$this->interceptor')
            );
        $ret->can('dispatch', 'public')
            ->rawArg('method', 'string')
            ->rawArg('uri', 'string')
            ->append($body);

        if ($this->interceptor !== null) {
            $ret->can('__construct')->append(
                (new Block)
                ->stmt(
                    Value::as('$this->interceptor ='),
                    Value::of($this->interceptor)
                )
            );
        }

        $ret->can('getInterceptor')->append(
            (new Block)->return(Value::as('$this->interceptor'))
        );

        return $ret;
    }

    /**
     * Generate static router, convert every dynamic call to handler/controller to static call.
     *
     * This method will generate an anonymous class which implements Fruit\RouteKit\Router.
     *
     *     file_put_content(
     *         'compiled_mux.php',
     *         (new \Fruit\compileKit\Block)
     *             ->return($mux->compile())
     *             ->asFile()
     *             ->render()
     *     );
     */
    public function compile(): Renderable
    {
        return $this->doCompile();
    }

    public function compileFile(bool $pretty = true): string
    {
        return (new Block)->return($this->compile())->asFile()->render($pretty);
    }

    public static function findRoute(string $method, string $uri, array $smap, array $vmap, array $fmap): array
    {
        $method = strtolower($method);
        if (!isset($smap[$method])) {
            throw new \Exception('unsupported method ' . $method);
        }
        $arr = explode('/', $uri);
        $sz = count($arr);
        $stack = array(array(1, 0, array()));
        while (count($stack) > 0) {
            list($i, $state, $params) = array_pop($stack);
            if ($i === $sz) {
                if (isset($fmap[$method][$state])) {
                    return array($fmap[$method][$state], $params);
                }

                continue;
            }
            $part = $arr[$i];
            if (isset($vmap[$method][$state])) {
                $p = $params;
                $p[] = $part;
                $stack[] = array($i+1, $vmap[$method][$state], $p);
            }
            if (isset($smap[$method][$state][$part])) {
                $stack[] = array($i+1, $smap[$method][$state][$part], $params);
            }
        }
        return array(null, array());
    }
}
