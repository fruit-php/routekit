<?php

namespace FruitTest\RouteKit;

class Interceptor implements \Fruit\RouteKit\Interceptor
{
    public function intercept(string $url, $obj, string $method)
    {
        $obj->inject('inject');
    }

    public static function __set_state()
    {
        return new self;
    }
}
