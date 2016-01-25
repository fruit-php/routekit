<?php

namespace Fruit\RouteKit;

use ReflectionParameter;
use Exception;

class Type
{
    /**
     * Convert type of parameters according to type hinting.
     *
     * It is recommanded to add type hinting to everything you wrote, including your handlers.
     * But url parameters are strings, so it needs to be converted.
     *
     * This method can only convert parameters to primitive types, so it's meaningless with php5 and hhvm.
     * We will skip this process if you are not using php7.
     */
    public static function typeConvert(array $params, array $pRefs)
    {
        static $shouldConvertType = null;
        if ($shouldConvertType === null) {
            $v = phpversion() + 0.0;
            // only php7+ have primitive type hinting
            $shouldConvertType = $v >= 7;
        }
        if (!$shouldConvertType) {
            return $params;
        }

        $size = count($pRefs);
        $ret = $params;
        $err = function(ReflectionParameter $ref, $type) {
            throw new TypeMismatchException($ref->getName(), $type);
        };

        foreach ($params as $idx => $param) {
            if ($idx >= $size) {
                break;
            }

            $pRef = $pRefs[$idx];
            if (!$pRef->hasType()) {
                $ret[$idx] = $param;
                continue;
            }

            $pType = $pRef->getType()->__toString();
            switch ($pType) {
            case 'int':
                if (!is_numeric($param) or strpos($param, '.') !== false) {
                    $err($pRef, $pType);
                }
                $ret[$idx] = $param + 0;
                break;
            case 'float':
                if (!is_numeric($param)) {
                    $err($pRef, $pType);
                }
                $ret[$idx] = $param + 0.0;
                break;
            case 'bool':
                switch (strtolower($param)) {
                case 'false':
                case 'null':
                case '0':
                    $ret[$idx] = false;
                    break;
                default:
                    $ret[$idx] = $param == true;
                }
                break;
            case 'string':
                $ret[$idx] = $param;
                break;
            default:
                throw new Exception(sprintf('The type of $%s is %s, which is not supported.', $pRef->getName(), $pType));
            }
        }
        return $ret;
    }
}
