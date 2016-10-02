<?php

namespace FruitTest\RouteKit;

use Fruit\RouteKit\Util;

class UtilTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Exception
     */
    public function testCompileClosure()
    {
        Util::compileCallable(function(){});
    }

    /**
     * @expectedException ReflectionException
     */
    public function testCompileUnknownFunc()
    {
        Util::compileCallable('nonexistfunction');
    }

    /**
     * @expectedException ReflectionException
     */
    public function testCompileUnknownClass()
    {
        Util::compileCallable('nonexistclass:somemethod');
    }

    /**
     * @expectedException ReflectionException
     */
    public function testCompileUnknownStaticMethod()
    {
        Util::compileCallable('FruitTest\RouteKit\UtilTestData::somemethod');
    }

    /**
     * @expectedException ReflectionException
     */
    public function testCompileUnknownMethod()
    {
        Util::compileCallable([new UtilTestData, 'somemethod']);
    }

    public function cbP()
    {
        return [
            ['var_dump', 'var_dump', 'function'],
            ['FruitTest\RouteKit\UtilTestData::b', '\FruitTest\RouteKit\UtilTestData::b', 'static (string)'],
            [['FruitTest\RouteKit\UtilTestData', 'b'], '\FruitTest\RouteKit\UtilTestData::b', 'static (array)'],
            [[new UtilTestData, 'b'], '\FruitTest\RouteKit\UtilTestData::b', 'static (object)'],
            [[new UtilTestData, 'a'], "(\\FruitTest\\RouteKit\\UtilTestData::__set_state(array(\n)))->a", 'object'],

        ];
    }

    /**
     * @dataProvider cbP
     */
    public function testCompileCallable($cb, $expect, $msg)
    {
        $this->assertEquals($expect.'()', Util::compileCallable($cb), $msg);
        $this->assertEquals($expect.'($a)', Util::compileCallable($cb, ['$a']), $msg);
        $this->assertEquals(
            $expect.'($a,$b)',
            Util::compileCallable($cb, ['$a', '$b']),
            $msg
        );
    }
}
