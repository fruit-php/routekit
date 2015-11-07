<?php

namespace FruitTest\RouteKit;

/**
 * Test handler for PHP7
 */
class Handler7
{
    private $data;

    public function __construct()
    {
        $this->data = func_get_args();
    }

    public function params(int $i, string $s, bool $b, float $f)
    {
        return func_get_args();
    }

    public function params2(array $a)
    {
        return func_get_args();
    }

    public function constructArgs()
    {
        return $this->data;
    }
}
