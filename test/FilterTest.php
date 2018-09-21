<?php

namespace FruitTest\RouteKit;

use Fruit\RouteKit\Mux;

class FilterTest extends \PHPUnit\Framework\TestCase
{
    private $cls = 'FruitTest\RouteKit\Filter';
    private function M($i, $o)
    {
        $cls = 'FruitTest\RouteKit\Handler';
        $mux = new Mux;
        $mux->setFilters($i, $o);
        $mux->get('/', array($cls, 'filter'));
        return $mux;
    }

    public function testInputPassNonCompile()
    {
        $mux = $this->M([[$this->cls, 'ipass']], []);
        $c = $this->cls;

        $actual = $mux->dispatch('get', '/');
        $this->assertEquals('original', $actual);
    }

    public function testInputBlockNonCompile()
    {
        $mux = $this->M([[$this->cls, 'iblock']], []);
        $c = $this->cls;

        $actual = $mux->dispatch('get', '/');
        $this->assertEquals('blocked', $actual);
    }

    public function testOutputNonCompile()
    {
        $mux = $this->M([], [[$this->cls, 'o']]);
        $c = $this->cls;

        $actual = $mux->dispatch('get', '/');
        $this->assertEquals('filtered', $actual);
    }

    public function testInputPassCompiled()
    {
        $mux = $this->M([[$this->cls, 'ipass']], []);
        $str = '$mymux = ' . $mux->compile()->render() . ';';
        eval($str);
        $c = $this->cls;

        $actual = $mymux->dispatch('get', '/');
        $this->assertEquals('original', $actual);
    }

    public function testInputBlockCompiled()
    {
        $mux = $this->M([[$this->cls, 'iblock']], []);
        $str = '$mymux = ' . $mux->compile()->render() . ';';
        eval($str);
        $c = $this->cls;

        $actual = $mymux->dispatch('get', '/');
        $this->assertEquals('blocked', $actual);
    }

    public function testOutputCompiled()
    {
        $mux = $this->M([], [[$this->cls, 'o']]);
        $str = '$mymux = ' . $mux->compile()->render() . ';';
        eval($str);
        $c = $this->cls;

        $actual = $mymux->dispatch('get', '/');
        $this->assertEquals('filtered', $actual);
    }
}
