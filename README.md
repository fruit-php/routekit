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
$mux->get('/foo', array('MyClass', 'myMethod'), array('args', 'of', 'constructor'));

class FOO {
    public function BAR($arg1, $arg2){}
    public function BAZ($arg1, $arg2){}
}
// uri variables are unnamed, ":x" is identical to ":"
$mux->get('/foo/:/:x/bar', array('FOO', 'BAR'));
$mux->get('/foo/:1/baz/:2', array('FOO', 'BAZ'));

// you cannot use closure as handler
// $mux->get('/', function(){});    // wrong!

// but you can use object if it can be serialized with var_export()
$mux->get('/', array(new FOO, 'BAR'));

// dispatch request
$mux->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['PATH_INFO']);

// generate class
file_put_content('router.php', $mux->compile('MyRouter'));

// use generated router class
$mux = require('router.php');
$mux->dispatch('GET', '/foo/123/abc/bar'); // (new Foo())->BAR('123', 'abc');
$mux->dispatch('GET', '/foo'); // (new MyClass('args', 'of', 'constructor'))->myMethod();
```

## Why fast

RouteKit gains performance in two ways: better data structure for rule-matching, and ability to convert dynamic call to static call.

### Matching process

RouteKit store routing rules in tree structure, so the matching speed will not be affected by how many rules you have. In other words, the matching process has constant time complexity.

More further, in generated custom router class, we use a Finite State Machine to do the matching process, eliminates all possible function calls. Function calls are much slower comparing to opcode actions (`if`, `switch`, assignments, arithmatic operations, etc.) and hashtable manuplating. In practice, the FSM approach can run 2x faster than tree lookup, or more than 20x faster comparing to array implementations.

### Static call

Most of router implementations split the dispatching work into two pieces: matching with rules, and execute the correct handler/controller. The matching part returns a callback, which is the handler/controller, and some other data. The execution part can call to that callback later, with `call_user_func` or `call_user_func_array`. And cufa is notorious for its **great** performance.

By generating custom router class, we generate codes according to the information you provided in routing rules. No more cufa, no more reflection, so no more performance penalty.

## Diagram

RouteKit can generate diagram to describe how we match the rules:

```php
$mux = new Fruit\RouteKit\Mux;

// add rules here
$mux->get('/', 'my_handler');

$diagrams = $mux->dot();
file_put_contents('get.gv', $diagrams['get']);
```

then

```sh
dot -Tsvg get.gv > get.svg
```

## License

Any version of MIT, GPL or LGPL.
