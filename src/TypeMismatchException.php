<?php

namespace Fruit\RouteKit;

class TypeMismatchException extends \Exception
{
    public $name;
    public $type;

    public function __construct($paramName, $needType)
    {
        parent::__construct(sprintf('Type mispatch for parameter $%s: need %s.', $paramName, $needType));
        $this->name = $paramName;
        $this->type = $needType;
    }
}
