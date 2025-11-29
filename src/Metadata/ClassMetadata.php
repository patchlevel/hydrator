<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use ReflectionClass;

/**
 * @psalm-type serialized array{
 *     className: class-string,
 *     properties: list<PropertyMetadata>,
 *     lazy: bool|null,
 *     extras: array<string, mixed>,
 * }
 * @template T of object = object
 */
final class ClassMetadata
{
    /** @var class-string<T> */
    public readonly string $className;

    /**
     * @param ReflectionClass<T>     $reflection
     * @param list<PropertyMetadata> $properties
     * @param array<string, mixed>   $extras
     */
    public function __construct(
        public readonly ReflectionClass $reflection,
        public readonly array $properties = [],
        public readonly bool|null $lazy = null,
        public array $extras = [],
    ) {
        $this->className = $reflection->getName();
    }

    public function propertyForField(string $name): PropertyMetadata
    {
        foreach ($this->properties as $property) {
            if ($property->fieldName === $name) {
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

    /** @return serialized */
    public function __serialize(): array
    {
        return [
            'className' => $this->className,
            'properties' => $this->properties,
            'lazy' => $this->lazy,
            'extras' => $this->extras,
        ];
    }

    /** @param serialized $data */
    public function __unserialize(array $data): void
    {
        $this->reflection = new ReflectionClass($data['className']);
        $this->properties = $data['properties'];
        $this->lazy = $data['lazy'];
        $this->extras = $data['extras'];
    }
}
