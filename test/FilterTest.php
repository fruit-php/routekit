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
        if (!class_exists('FilteredRouteInputPass')) {
            $str = $mux->compile('FilteredRouteInputPass');
            eval(substr($str, 5));
        }
        $mux = new \FilteredRouteInputPass;
        $c = $this->cls;

        $actual = $mux->dispatch('get', '/');
        $this->assertEquals('original', $actual);
    }

    public function testInputBlockCompiled()
    {
        $mux = $this->M([[$this->cls, 'iblock']], []);
        if (!class_exists('FilteredRouteInputBlock')) {
            $str = $mux->compile('FilteredRouteInputBlock');
            eval(substr($str, 5));
        }
        $mux = new \FilteredRouteInputBlock;
        $c = $this->cls;

        $actual = $mux->dispatch('get', '/');
        $this->assertEquals('blocked', $actual);
    }

    public function testOutputCompiled()
    {
        $mux = $this->M([], [[$this->cls, 'o']]);
        if (!class_exists('FilteredRouteOutput')) {
            $str = $mux->compile('FilteredRouteOutput');
            eval(substr($str, 5));
        }
        $mux = new \FilteredRouteOutput;
        $c = $this->cls;

        $actual = $mux->dispatch('get', '/');
        $this->assertEquals('filtered', $actual);
    }
}
