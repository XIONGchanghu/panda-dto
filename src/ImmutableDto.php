<?php

namespace Pandaxxw\Dto;

class ImmutableDto
{
    protected Dto $dataTransferObject;

    public function __construct(Dto $dataTransferObject)
    {
        foreach (get_object_vars($dataTransferObject) as $k => $v) {
            if (is_subclass_of($v, Dto::class)) {
                $dataTransferObject->{$k} = new self($v);
            };
        }
        $this->dataTransferObject = $dataTransferObject;
    }

    public function __set($name, $value)
    {
        throw DtoError::immutable($name);
    }

    public function __get($name)
    {
        return $this->dataTransferObject->{$name};
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->dataTransferObject, $name], $arguments);
    }
}
