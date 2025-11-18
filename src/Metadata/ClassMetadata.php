<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use ReflectionClass;

/**
 * @psalm-type serialized array{
 *     className: class-string,
 *     properties: list<PropertyMetadata>,
 *     postHydrateCallbacks: list<CallbackMetadata>,
 *     preExtractCallbacks: list<CallbackMetadata>,
 *     lazy: bool|null,
 *     extras: array<string, mixed>,
 * }
 * @template T of object = object
 */
final class ClassMetadata
{
    /**
     * @param ReflectionClass<T>     $reflection
     * @param list<PropertyMetadata> $properties
     * @param list<CallbackMetadata> $postHydrateCallbacks
     * @param list<CallbackMetadata> $preExtractCallbacks
     * @param array<string, mixed>   $extras
     */
    public function __construct(
        public readonly ReflectionClass $reflection,
        public readonly array $properties = [],
        public readonly array $postHydrateCallbacks = [],
        public readonly array $preExtractCallbacks = [],
        public readonly bool|null $lazy = null,
        public array $extras = [],
    ) {
    }

    /** @return class-string<T> */
    public function className(): string
    {
        return $this->reflection->getName();
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
            'className' => $this->reflection->getName(),
            'properties' => $this->properties,
            'postHydrateCallbacks' => $this->postHydrateCallbacks,
            'preExtractCallbacks' => $this->preExtractCallbacks,
            'lazy' => $this->lazy,
            'extras' => $this->extras,
        ];
    }

    /** @param serialized $data */
    public function __unserialize(array $data): void
    {
        $this->reflection = new ReflectionClass($data['className']);
        $this->properties = $data['properties'];
        $this->postHydrateCallbacks = $data['postHydrateCallbacks'];
        $this->preExtractCallbacks = $data['preExtractCallbacks'];
        $this->lazy = $data['lazy'];
        $this->extras = $data['extras'];
    }
}
