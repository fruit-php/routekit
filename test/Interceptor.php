<?php

namespace FruitTest\RouteKit;

class Interceptor implements \Fruit\RouteKit\Interceptor
{
    public static function __set_state(array $arr)
    {
        return new self;
    }

    public function generate()
    {
        return function($url, Handler $obj, $method) {
            $obj->inject("inject");
        };
    }
}
