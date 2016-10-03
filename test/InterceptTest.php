<?php

namespace FruitTest\RouteKit;

use Fruit\RouteKit\Mux;

class InterceptTest extends \PHPUnit_Framework_TestCase
{
    private $cls = 'FruitTest\RouteKit\Interceptor';
    private function M()
    {
        $cls = 'FruitTest\RouteKit\Handler';
        $mux = new Mux;
        $mux->get('/', array($cls, 'inj'));
        return $mux;
    }

    public function testObjNonCompile()
    {
        $mux = $this->M();
        $c = $this->cls;
        $mux->setInterceptor([new $c, 'obj']);

        $actual = $mux->dispatch('get', '/');
        $this->assertEquals('inject', $actual);
    }

    public function testStaticNonCompile()
    {
        $mux = $this->M();
        $mux->setInterceptor([$this->cls, 'stati']);

        $actual = $mux->dispatch('get', '/');
        $this->assertEquals('inject', $actual);
    }

    public function testObjCompiled()
    {
        $mux = $this->M();
        $c = $this->cls;
        $mux->setInterceptor([new $c, 'obj']);
        if (!class_exists('InterceptedRouteObj')) {
            $str = $mux->compile('InterceptedRouteObj');
            eval(substr($str, 5));
        }
        $mux = new \InterceptedRouteObj;

        $actual = $mux->dispatch('get', '/');
        $this->assertEquals('inject', $actual);
    }

    public function testStaticCompiled()
    {
        $mux = $this->M();
        $mux->setInterceptor([$this->cls, 'stati']);
        if (!class_exists('InterceptedRouteStatic')) {
            $str = $mux->compile('InterceptedRouteStatic');
            eval(substr($str, 5));
        }
        $mux = new \InterceptedRouteStatic;

        $actual = $mux->dispatch('get', '/');
        $this->assertEquals('inject', $actual);
    }
}
