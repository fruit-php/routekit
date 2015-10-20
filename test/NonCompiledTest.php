<?php

namespace FruitTest\RouteKit;

use Fruit\RouteKit\Mux;

class NonCompiledTest extends \PHPUnit_Framework_TestCase
{
    private $mux;

    private function M()
    {
        $cls = 'FruitTest\RouteKit\Handler';
        if ($this->mux == null) {
            $mux = new Mux;
            $mux->get('/', array($cls, 'get'));
            $mux->post('/', array($cls, 'post'));

            $mux->get('/basic', array($cls, 'basic'));
            $mux->get('/params/:', array($cls, 'params'));
            $mux->get('/params/:/2/:', array($cls, 'params'));

            $mux->get('/init', array($cls, 'constructArgs'), array(1, 2));
            $this->mux = $mux;
        }

        return $this->mux;
    }

    public function testRoot()
    {
        foreach (array('get', 'post') as $m) {
            $actual = $this->M()->dispatch($m, '/');
            $this->assertEquals($m, $actual);
        }
    }

    public function testBasic()
    {
        $this->assertEquals(1, $this->M()->dispatch('GET', '/basic'));
    }

    public function testParams()
    {
        $this->assertEquals(array('1'), $this->M()->dispatch('GET', '/params/1'));
        $this->assertEquals(array('foo', 'bar'), $this->M()->dispatch('GET', '/params/foo/2/bar'));
    }

    public function testInit()
    {
        $this->assertEquals(array(1, 2), $this->M()->dispatch('GET', '/init'));
    }
}
