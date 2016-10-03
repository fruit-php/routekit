# RouteKit

This package is part of Fruit Framework, requires PHP 7.

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

// use injector to do DI tasks
function di($url, $obj, $method) {
	$obj->setDB($db);
}
$mux->setInjector('di');
$mux->get('/', array(new FOO, 'BAR'));
$mux->dispatch('GET', '/'); // $obj = new FOO; di('/', $obj, 'BAR'); $obj->BAR();

// auth middleware, implements by input filter
function checkAuth($method, $url, $cb, $params) {
    if (!authed()) {
		header('Location: /login');
		return 'login first';
	}
}
$mux->setFilters(['checkAuth'], []);
$mux->get('/', array(new FOO, 'BAR'));
$mux->dispatch('GET', '/'); // redirect to /login if not logged in

// CORS middleware, implements by output filter
function addCORS($result) {
    // few lines of code adding CORS headers here
	
	return $result;
}
$mux->setFilters([], ['addCORS']);
$mux->get('/', array(new FOO, 'BAR'));
$mux->dispatch('GET', '/'); // client will receive CORS header!

// Templating middleware
function handler() {
	// real codes
	
	return ['view' => 'index.tmpl', 'data' => $result];
}
function forgeView($result) {
	return Template::load($result['view'])->render($result['data']);
}
$mux->setFilters([], ['addCORS']);
$mux->get('/', 'handler');
$mux->dispatch('GET', '/');
```

## Why fast

RouteKit gains performance in two ways: better data structure for rule-matching, and ability to convert dynamic call to static call.

### Matching process

RouteKit store routing rules in tree structure, so the matching speed will not be affected by how many rules you have. In other words, the matching process has constant time complexity.

More further, in generated custom router class, we use a Finite State Machine to do the matching process, eliminates all possible function calls. Function calls are much slower comparing to opcode actions (`if`, `switch`, assignments, arithmatic operations, etc.) and hashtable manuplating. In practice, the FSM approach can run more than 20x faster comparing to array implementations.

### Static call

Most of router implementations splits the dispatching work into two pieces: matching with rules to grab correct handler, and execute it with cufa; And cufa is notorious for its **great** performance.

By generating custom router class, we generate codes according to the information you provided in routing rules. No more cufa, no more reflection, so no more performance penalty.

Since codes are mostly generated using `var_export`, some cases are not supported:

- Classes cannot be restored using `__set_state`.
- Closures and anonymouse functions.
- Not accessible methods. (Methods not `public`)
- Special variables like resources.

Generated class should be thread-safe, since all properties and methods are static.

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

## Type converting

You can add type hintings to your handler, RouteKit will automatically do the type checking and converting for you. Currently we support only `int`, `float`, `bool` and `string`. We will not check/convert the parameters without type-hinted.

In non-compiled version, this is done by `ReflectionParameter::getType()`, so it would cost some performance for fetching reflection data.

In compiled version, we grab the type info at compile time, so there is no performance penalty excepts the type checking and converting works.

```php
function handlerOK(int $i) {}
function handlerFAIL(array $i) {}
$mux->get('/ok/:', 'handlerOK');
$mux->get('/fail/:', 'handlerFAIL');

$mux->dispatch('GET', '/ok/1'); // works
$mux->dispatch('GET', '/ok/hello'); // throw a Fruit\RouteKit\TypeMismatchException
$mux->dispatch('GET', '/fail/1'); // throw an Exception with messages telling you array type is not supported
$mux->compile(); // throw an Exception with messages telling you array type is not supported
```

## Injector and filter

### Injectors

Injector is mean to solve "global variable" problem: you can _inject_ global data into your handler object via the interface you defined.

### Input filters

Input filter is mean to filter the control flow. Permission validating layer should be implemented here.

An input filter accepts exactly 4 parameters:

- HTTP method
- URL
- A callable represents the handler
- Array of parameters going to be passed to handler.

Returning anything other than `NULL` causes dispatching process to be interrupted, and the result of filtering is returned immediately.

While PHP syntax allowing you to modify the 4 params, you SHOULD NOT modify them. That's because input filter is not mean to filtering input data.

### Output filters

Output filter is mean to filter the result. If you want to refine the result or HTTP headers, you're at the right place.

An output filter accepts only 1 prameter, which is the returned data from handler: you modify it, send or delete some headers, and return the result back. The most common example is adding CORS headers or forge real output by templating system.

## Dispatching flow

1. Searching routing tree for currect handler and gather url parameters.
2. Executing input filters, decide whether to execute further.
3. If any input filter blocks execution, return immediately.
4. Modifying handler's internal state using injector.
5. Executes handler.
6. Manipulate result by executing output filters.

## License

Any version of MIT, GPL or LGPL.
