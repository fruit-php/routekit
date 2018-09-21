<?php

namespace FruitTest\RouteKit;

use Fruit\RouteKit\Mux;

class InterceptTest extends \PHPUnit\Framework\TestCase
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
        $str = '$mymux = ' . $mux->compile()->render() . ';';
        eval($str);

        $actual = $mymux->dispatch('get', '/');
        $this->assertEquals('inject', $actual);
    }
}
