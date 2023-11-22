<?php

declare(strict_types=1);

namespace Pandaxxw\Dto;

use ReflectionClass;
use ReflectionProperty;

abstract class Dto
{
    protected bool $ignoreMissing = false;

    protected array $exceptKeys = [];

    protected array $onlyKeys = [];

    /**
     * @param array $parameters
     *
     * @return \Pandaxxw\Dto\ImmutableDto|static
     */
    public static function immutable(array $parameters = []): ImmutableDto
    {
        return new ImmutableDto(new static($parameters));
    }

    /**
     * @param array $arrayOfParameters
     *
     * @return \Pandaxxw\Dto\ImmutableDto[]|static[]
     */
    public static function arrayOf(array $arrayOfParameters): array
    {
        return array_map(
            function ($parameters) {
                return new static($parameters);
            },
            $arrayOfParameters
        );
    }

    public function __construct(array $parameters = [])
    {
        $validators = $this->getFieldValidators();

        $valueCaster = $this->getValueCaster();

        /** string[] */
        $invalidTypes = [];

        foreach ($validators as $field => $validator) {
            if (
                ! isset($parameters[$field])
                && ! $validator->hasDefaultValue
                && ! $validator->isNullable
            ) {
                throw DtoError::uninitialized(
                    static::class,
                    $field
                );
            }

            $value = $parameters[$field] ?? $this->{$field} ?? null;

            $value = $this->castValue($valueCaster, $validator, $value);

            if (! $validator->isValidType($value)) {
                $invalidTypes[] = DtoError::invalidTypeMessage(
                    static::class,
                    $field,
                    $validator->allowedTypes,
                    $value
                );

                continue;
            }

            $this->{$field} = $value;

            unset($parameters[$field]);
        }

        if ($invalidTypes) {
            DtoError::invalidTypes($invalidTypes);
        }

        if (! $this->ignoreMissing && count($parameters)) {
            throw DtoError::unknownProperties(array_keys($parameters), static::class);
        }
    }

    public function all(): array
    {
        $data = [];

        $class = new ReflectionClass(static::class);

        $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $reflectionProperty) {
            // Skip static properties
            if ($reflectionProperty->isStatic()) {
                continue;
            }

            $data[$reflectionProperty->getName()] = $reflectionProperty->getValue($this);
        }

        return $data;
    }

    /**
     * @param string ...$keys
     *
     * @return static
     */
    public function only(string ...$keys): Dto
    {
        $dataTransferObject = clone $this;

        $dataTransferObject->onlyKeys = [...$this->onlyKeys, ...$keys];

        return $dataTransferObject;
    }

    /**
     * @param string ...$keys
     *
     * @return static
     */
    public function except(string ...$keys): Dto
    {
        $dataTransferObject = clone $this;

        $dataTransferObject->exceptKeys = [...$this->exceptKeys, ...$keys];

        return $dataTransferObject;
    }

    public function toArray(): array
    {
        if (count($this->onlyKeys)) {
            $array = Arr::only($this->all(), $this->onlyKeys);
        } else {
            $array = Arr::except($this->all(), $this->exceptKeys);
        }

        $array = $this->parseArray($array);

        return $array;
    }

    protected function parseArray(array $array): array
    {
        foreach ($array as $key => $value) {
            if (
                $value instanceof Dto
                || $value instanceof DtoCollection
            ) {
                $array[$key] = $value->toArray();

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $array[$key] = $this->parseArray($value);
        }

        return $array;
    }

    /**
     * @param \ReflectionClass $class
     *
     * @return \Pandaxxw\Dto\FieldValidator[]
     */
    protected function getFieldValidators(): array
    {
        return DTOCache::resolve(static::class, function () {
            $class = new ReflectionClass(static::class);

            $properties = [];

            foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
                // Skip static properties
                if ($reflectionProperty->isStatic()) {
                    continue;
                }

                $field = $reflectionProperty->getName();

                $properties[$field] = FieldValidator::fromReflection($reflectionProperty);
            }

            return $properties;
        });
    }

    /**
     * @param \Pandaxxw\Dto\ValueCaster $valueCaster
     * @param \Pandaxxw\Dto\FieldValidator $fieldValidator
     * @param mixed $value
     *
     * @return mixed
     */
    protected function castValue(ValueCaster $valueCaster, FieldValidator $fieldValidator, $value)
    {
        if (is_array($value)) {
            return $valueCaster->cast($value, $fieldValidator);
        }

        return $value;
    }

    protected function getValueCaster(): ValueCaster
    {
        return new ValueCaster();
    }
}
