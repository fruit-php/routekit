<?php

namespace Fruit\RouteKit;

/**
 * Interceptor defines an interface for you to do something before executing controller.
 *
 * Interceptor can work with only controller class. If you use function as controller, that would not be interceptable.
 *
 * RouteKit will use var_export to compile Interceptor, be sure you have __set_state() method defined if needed.
 */
interface Interceptor
{
    /**
     * generate() generates an function, which accepts three parameters: raw url, instance of matched class and method.
     * You can do some modifications to that controller instance in the function, but not subtitude it.
     *
     * You can also add type-hinting on the instance, only that kind of object will pass to your interception function.
     *
     * @return function(string $url, object $instance, string $method)
     */
    public function generate();
}
