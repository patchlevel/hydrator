<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use ReflectionClass;
use ReflectionParameter;

use function array_values;

/**
 * @psalm-type serialized array{
 *     className: class-string<T>,
 *     properties: array<string, PropertyMetadata>,
 *     dataSubjectIdField: string|null,
 *     postHydrateCallbacks: list<CallbackMetadata>,
 *     preExtractCallbacks: list<CallbackMetadata>,
 *     lazy: bool|null,
 *     extras: array<string, mixed>
 * }
 * @template T of object = object
 */
final class ClassMetadata
{
    /** @var class-string<T> */
    public readonly string $className;

    /** @var array<string, PropertyMetadata> */
    public readonly array $properties;

    /** @var array<string, ReflectionParameter>|null */
    private array|null $promotedConstructorDefaults = null;

    /**
     * @param ReflectionClass<T>     $reflection
     * @param list<PropertyMetadata> $properties
     * @param list<CallbackMetadata> $postHydrateCallbacks
     * @param list<CallbackMetadata> $preExtractCallbacks
     * @param array<string, mixed>   $extras
     */
    public function __construct(
        public readonly ReflectionClass $reflection,
        array $properties = [],
        public string|null $dataSubjectIdField = null,
        public array $postHydrateCallbacks = [],
        public array $preExtractCallbacks = [],
        public bool|null $lazy = null,
        public array $extras = [],
    ) {
        $this->className = $reflection->getName();

        $map = [];

        foreach ($properties as $property) {
            $map[$property->propertyName] = $property;
        }

        $this->properties = $map;
    }

    /** @return ReflectionClass<T> */
    public function reflection(): ReflectionClass
    {
        return $this->reflection;
    }

    /** @return class-string<T> */
    public function className(): string
    {
        return $this->className;
    }

    /** @return list<PropertyMetadata> */
    public function properties(): array
    {
        return array_values($this->properties);
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

    /** @return array<string, ReflectionParameter> */
    public function promotedConstructorDefaults(): array
    {
        if ($this->promotedConstructorDefaults !== null) {
            return $this->promotedConstructorDefaults;
        }

        $constructor = $this->reflection->getConstructor();

        if (!$constructor) {
            return $this->promotedConstructorDefaults = [];
        }

        $result = [];

        foreach ($constructor->getParameters() as $parameter) {
            if (!$parameter->isPromoted() || !$parameter->isDefaultValueAvailable()) {
                continue;
            }

            $result[$parameter->getName()] = $parameter;
        }

        return $this->promotedConstructorDefaults = $result;
    }

    /** @return serialized */
    public function __serialize(): array
    {
        return [
            'className' => $this->className,
            'properties' => $this->properties,
            'dataSubjectIdField' => $this->dataSubjectIdField,
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
        $this->dataSubjectIdField = $data['dataSubjectIdField'];
        $this->postHydrateCallbacks = $data['postHydrateCallbacks'];
        $this->preExtractCallbacks = $data['preExtractCallbacks'];
        $this->lazy = $data['lazy'];
        $this->extras = $data['extras'];
    }
}
