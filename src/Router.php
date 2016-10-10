<?php

namespace Fruit\RouteKit;

interface Router
{
    public function dispatch(string $method, string $uri);
    public function getInterceptor();
}
