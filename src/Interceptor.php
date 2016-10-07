<?php

namespace Fruit\RouteKit;

interface Interceptor
{
    public function intercept($url, $obj, $method);
}
