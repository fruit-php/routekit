<?php

namespace Fruit\RouteKit;

use ReflectionClass;
use ReflectionFunction;
use Exception;

class Util
{
    public static function reflectionCallable($cb)
    {
        if (!is_callable($cb, true, $cn)) {
            throw new Exception('compileCallable accepts only callable');
        }
        if ($cn === 'Closure::__invoke') {
            // closure
            return [new ReflectionFunction($cb)];
        }
        if (!is_array($cb)) {
            if (strpos($cb, '::') === false) {
                // function
                return [new ReflectionFunction($cb)];
            }
            $cb = explode('::', $cb);
        }

        $c = new ReflectionClass($cb[0]);
        $f = $c->getMethod($cb[1]);
        return [$f, $c];
    }
    public static function compileCallable($cb, array $params = [])
    {
        $res = self::reflectionCallable($cb);
        if (count($res) === 1) {
            // function or closure
            $f = $res[0];
            if ($f->isClosure()) {
                throw new Exception('Cannot compile closure');
            }
            return sprintf('%s(%s)', $f->getName(), implode(',', $params));
        }

        list($f, $ref) = $res;
        $tmpl = '%s::%s(%s)';
        $c = "\\" . $ref->getName();

        if (is_object($cb[0]) and !$f->isStatic()) {
            $c = "\\" . var_export($cb[0], true);
            $tmpl = '(%s)->%s(%s)';
        }

        return sprintf($tmpl, $c, $f->getName(), implode(',', $params));
    }
}
