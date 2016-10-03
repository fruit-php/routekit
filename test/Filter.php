<?php

namespace FruitTest\RouteKit;

class Filter
{
    public static function ipass($m, $u, $cb, $p)
    {
    }

    public static function iblock($m, $u, $cb, $p)
    {
        return 'blocked';
    }

    public static function o($result)
    {
        return 'filtered';
    }
}
