# RouteKit

This package is part of Fruit Framework.

RouteKit is a fast router implementation. It stores your routing rules in a tree structure, and you can make it even faster by generating a router class with builtin class generator.

[![Build Status](https://travis-ci.org/Ronmi/fruit-routekit.svg)](https://travis-ci.org/Ronmi/fruit-routekit)

## Synopsis

```php
$mux = new Fruit\RouteKit\Mux;

// Create routing rules

$mux->get('/', array('MyClass', 'myMethod'));
// static method
$mux->get('/', 'Foo::Bar');
// function
$mux->get('/', 'foobar');
$mux->get('/', foobar);
// create handler/controller instance with constructor arguments.
$mux->get('/foo', array('MyClass', 'myMethod'), array('args, 'of', 'constructor'));

class FOO {
    public function BAR($arg1, $arg2){}
    public function BAZ($arg1, $arg2){}
}
// uri variables are unnamed, ":x" is identical to ":"
$mux->get('/foo/:/:x/bar', array('FOO', 'BAR'));
$mux->get('/foo/:1/baz/:2', array('FOO', 'BAZ'));

// you cannot use closure as handler
// $mux->get('/', function(){});    // wrong!

// dispatch request
$mux->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['PATH_INFO']);

// generate class
file_put_content('router.php', $mux->compile('MyRouter'));

// use generated router class
$mux = require('router.php');
$mux->dispatch('GET', '/foo/123/abc/bar'); // (new Foo())->BAR('123', 'abc');
$mux->dispatch('GET', '/foo'); // (new MyClass('args', 'of', 'constructor'))->myMethod();
```

## License

Any version of MIT, GPL or LGPL.
