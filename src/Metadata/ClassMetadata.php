<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use ReflectionClass;

/** @template T of object */
final class ClassMetadata
{
    /**
     * @param ReflectionClass<T>     $reflection
     * @param list<PropertyMetadata> $properties
     */
    public function __construct(
        private readonly ReflectionClass $reflection,
        private readonly array $properties = [],
    ) {
    }

    /** @return ReflectionClass<T> */
    public function reflection(): ReflectionClass
    {
        return $this->reflection;
    }

    /** @return class-string<T> */
    public function className(): string
    {
        return $this->reflection->getName();
    }

    /** @return list<PropertyMetadata> */
    public function properties(): array
    {
        return $this->properties;
    }

    public function propertyForField(string $name): PropertyMetadata
    {
        foreach ($this->properties as $property) {
            if ($property->fieldName() === $name) {
                return $property;
            }
        }

        throw PropertyMetadataNotFound::withName($name);
    }

    /** @return T */
    public function newInstance(): object
    {
        return $this->reflection->newInstanceWithoutConstructor();
    }

    /** @return array{className: class-string<T>, properties: list<PropertyMetadata>} */
    public function __serialize(): array
    {
        return [
            'className' => $this->reflection->getName(),
            'properties' => $this->properties,
        ];
    }

    /** @param array{className: class-string<T>, properties: list<PropertyMetadata>} $data */
    public function __unserialize(array $data): void
    {
        $this->reflection = new ReflectionClass($data['className']);
        $this->properties = $data['properties'];
    }
}
