<?php

namespace Fruit\RouteKit;

interface Router
{
    public function dispatch($method, $uri);
}
