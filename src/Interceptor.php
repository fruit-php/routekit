<?php

namespace Fruit\RouteKit;

interface Interceptor
{
    public function intercept(string $url, $obj, string $method);
}
