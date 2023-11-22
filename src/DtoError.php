<?php

declare(strict_types=1);

namespace Pandaxxw\Dto;

use TypeError;

class DtoError extends TypeError
{
    public static function unknownProperties(array $properties, string $className): DtoError
    {
        $propertyNames = implode('`, `', $properties);

        return new self("Public properties `{$propertyNames}` not found on {$className}");
    }

    public static function invalidTypes(array $invalidTypes): DtoError
    {
        $msg = count($invalidTypes) > 1
            ? "The following invalid types were encountered:\n" . implode("\n", $invalidTypes) . "\n"
            : "Invalid type: {$invalidTypes[0]}.";

        throw new self($msg);
    }

    public static function invalidTypeMessage(
        string $class,
        string $field,
        array $expectedTypes,
        $value
    ): string {
        $currentType = gettype($value);

        if ($value === null) {
            $value = 'null';
        }

        if (is_object($value)) {
            $value = get_class($value);
        }

        if (is_array($value)) {
            $value = 'array';
        }

        $expectedTypes = implode(', ', $expectedTypes);

        if ($value === $currentType) {
            $instead = "instead got value `{$value}`.";
        } else {
            $instead = "instead got value `{$value}`, which is {$currentType}.";
        }

        return "expected `{$class}::{$field}` to be of type `{$expectedTypes}`, {$instead}";
    }

    public static function uninitialized(string $class, string $field): DtoError
    {
        return new self("Non-nullable property `{$class}::{$field}` has not been initialized.");
    }

    public static function immutable(string $property): DtoError
    {
        return new self("Cannot change the value of property {$property} on an immutable data transfer object");
    }

    public static function untypedCollection(string $class): DtoError
    {
        return new self("Collection class `{$class}` has no defined array type.");
    }
}
