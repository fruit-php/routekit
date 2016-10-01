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
            $mux->get('/params', array($cls, 'params'));
            $mux->get('/params/:', array($cls, 'params'));
            $mux->get('/params/:/2/:', array($cls, 'params'));
            $mux->get('/params/2/:/:', array($cls, 'params'));

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
        $this->assertEquals(array(), $this->M()->dispatch('GET', '/params'));
        $this->assertEquals(array('1'), $this->M()->dispatch('GET', '/params/1'));
        $this->assertEquals(array('foo', 'bar'), $this->M()->dispatch('GET', '/params/foo/2/bar'));
        $this->assertEquals(array('foo', 'bar'), $this->M()->dispatch('GET', '/params/2/foo/bar'));
    }

    public function testInit()
    {
        $this->assertEquals(array(1, 2), $this->M()->dispatch('GET', '/init'));
    }

    /**
     * @requires PHP 7
     */
    public function testParamTypes()
    {
        $cls = 'FruitTest\RouteKit\Handler7';
        $mux = new Mux;
        $mux->get('/p/:/:/:/:', array($cls, 'params'));
        $actual = $mux->dispatch('GET', '/p/1/2/true/4.5');
        $this->assertEquals(array(1, '2', true, 4.5), $actual);
        $actual = $mux->dispatch('GET', '/p/-1/2/1/-4.5');
        $this->assertEquals(array(-1, '2', true, -4.5), $actual);
        $actual = $mux->dispatch('GET', '/p/-1/2/false/-4.5');
        $this->assertEquals(array(-1, '2', false, -4.5), $actual);
        $actual = $mux->dispatch('GET', '/p/-1/2/null/-4.5');
        $this->assertEquals(array(-1, '2', false, -4.5), $actual);
        $actual = $mux->dispatch('GET', '/p/-1/2/0/-4.5');
        $this->assertEquals(array(-1, '2', false, -4.5), $actual);
    }

    public function wrongTypeP()
    {
        return array(
            // /p/int/string/bool/float
            array('/p/1.5/2/3/4.5'), // not int
            array('/p/orz/2/3/4.5'), // not int
            array('/p/1/2/3/orz'), // not float
        );
    }

    /**
     * @requires PHP 7
     * @expectedException Fruit\RouteKit\TypeMismatchException
     * @dataProvider wrongTypeP
     */
    public function testParamWrongType($uri)
    {
        $cls = 'FruitTest\RouteKit\Handler7';
        $mux = new Mux;
        $mux->get('/p/:/:/:/:', array($cls, 'params'));
        $actual = $mux->dispatch('GET', $uri);
    }

    /**
     * @requires PHP 7
     * @expectedException Exception
     */
    public function testUnsupportedType()
    {
        $cls = 'FruitTest\RouteKit\Handler7';
        $mux = new Mux;
        $mux->get('/p/:', array($cls, 'params2'));
        $actual = $mux->dispatch('GET', '/p/a');
    }
}
