<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use ReflectionClass;
use RuntimeException;

/**
 * @psalm-type serialized array{
 *     className: class-string,
 *     properties: list<PropertyMetadata>,
 *     postHydrateCallbacks: list<CallbackMetadata>,
 *     preExtractCallbacks: list<CallbackMetadata>,
 *     lazy: bool|null,
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
     */
    public function __construct(
        private readonly ReflectionClass $reflection,
        private readonly array $properties = [],
        private readonly array $postHydrateCallbacks = [],
        private readonly array $preExtractCallbacks = [],
        private readonly bool|null $lazy = null,
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

    public function lazy(): bool|null
    {
        return $this->lazy;
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

    public function hasSubjectIdIdentifier(string $subjectIdIdentifier): bool
    {
        foreach ($this->properties as $property) {
            if ($property->subjectIdIdentifier() === $subjectIdIdentifier) {
                return true;
            }
        }

        return false;
    }

    public function getSubjectIdFieldName(string $subjectIdIdentifier): string
    {
        foreach ($this->properties as $property) {
            if ($property->subjectIdIdentifier() === $subjectIdIdentifier) {
                return $property->fieldName();
            }
        }

        throw new RuntimeException('No subject id');
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
    }
}
