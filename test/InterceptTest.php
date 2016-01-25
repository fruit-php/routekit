<?php

namespace FruitTest\RouteKit;

use Fruit\RouteKit\Mux;

class InterceptTest extends \PHPUnit_Framework_TestCase
{
    private $mux;

    private function M()
    {
        $cls = 'FruitTest\RouteKit\Handler';
        if ($this->mux == null) {
            $mux = new Mux;
            $mux->setInterceptor(new Interceptor);
            $mux->get('/', array($cls, 'inj'));
            $this->mux = $mux;
        }

        return $this->mux;
    }

    private function C()
    {
        if (!class_exists('InterceptedRoute')) {
            $mux = $this->M();
            $str = $mux->compile('InterceptedRoute');
            eval(substr($str, 5));
        }
        return new \InterceptedRoute;
    }

    public function testNonCompile()
    {
        $actual = $this->M()->dispatch('get', '/');
        $this->assertEquals('inject', $actual);
    }

    public function testCompiled()
    {
        $actual = $this->C()->dispatch('get', '/');
        $this->assertEquals('inject', $actual);
    }

}
