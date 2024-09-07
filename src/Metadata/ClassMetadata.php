<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use ReflectionClass;

/**
 * @psalm-type serialized array{
 *     className: class-string,
 *     properties: list<PropertyMetadata>,
 *     dataSubjectIdField: string|null,
 *     postHydrateCallbacks: list<CallbackMetadata>,
 *     preExtractCallbacks: list<CallbackMetadata>,
 * }
 * @template T of object
 */
final class ClassMetadata
{
    /**
     * @param ReflectionClass<T>     $reflection
     * @param list<PropertyMetadata> $properties
     * @param list<CallbackMetadata> $postHydrateCallbacks
     * @param list<CallbackMetadata> $preExtractCallbacks
     */
    public function __construct(
        private readonly ReflectionClass $reflection,
        private readonly array $properties = [],
        private readonly string|null $dataSubjectIdField = null,
        private readonly array $postHydrateCallbacks = [],
        private readonly array $preExtractCallbacks = [],
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

    /** @return list<CallbackMetadata> */
    public function postHydrateCallbacks(): array
    {
        return $this->postHydrateCallbacks;
    }

    /** @return list<CallbackMetadata> */
    public function preExtractCallbacks(): array
    {
        return $this->preExtractCallbacks;
    }

    public function dataSubjectIdField(): string|null
    {
        return $this->dataSubjectIdField;
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

    /** @return serialized */
    public function __serialize(): array
    {
        return [
            'className' => $this->reflection->getName(),
            'properties' => $this->properties,
            'dataSubjectIdField' => $this->dataSubjectIdField,
            'postHydrateCallbacks' => $this->postHydrateCallbacks,
            'preExtractCallbacks' => $this->preExtractCallbacks,
        ];
    }

    /** @param serialized $data */
    public function __unserialize(array $data): void
    {
        $this->reflection = new ReflectionClass($data['className']);
        $this->properties = $data['properties'];
        $this->dataSubjectIdField = $data['dataSubjectIdField'];
        $this->postHydrateCallbacks = $data['postHydrateCallbacks'];
        $this->preExtractCallbacks = $data['preExtractCallbacks'];
    }
}
