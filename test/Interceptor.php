<?php

namespace FruitTest\RouteKit;

class Interceptor
{
    public function obj($url, $obj, $method)
    {
        $obj->inject('inject');
    }

    public static function stati($url, $obj, $method)
    {
        $obj->inject('inject');
    }

    public static function __set_state()
    {
        return new self;
    }
}
