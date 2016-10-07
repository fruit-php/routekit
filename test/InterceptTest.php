<?php

namespace FruitTest\RouteKit;

use Fruit\RouteKit\Mux;

class InterceptTest extends \PHPUnit_Framework_TestCase
{
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
        $mux->setInterceptor(new Interceptor);

        $actual = $mux->dispatch('get', '/');
        $this->assertEquals('inject', $actual);
    }

    public function testObjCompiled()
    {
        $mux = $this->M();
        $mux->setInterceptor(new Interceptor);
        if (!class_exists('InterceptedRouteObj')) {
            $str = $mux->compile('InterceptedRouteObj');
            eval(substr($str, 5));
        }
        $mux = new \InterceptedRouteObj;

        $actual = $mux->dispatch('get', '/');
        $this->assertEquals('inject', $actual);
    }
}
