<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use ReflectionClass;

/**
 * @template T of object
 */
final class ClassMetadata
{
    /**
     * @param ReflectionClass<T> $reflection
     */
    public function __construct(
        private readonly ReflectionClass $reflection,
        /** @var array<string, PropertyMetadata> */
        private readonly array $properties = [],
    ) {
    }

    /**
     * @return ReflectionClass<T>
     */
    public function reflection(): ReflectionClass
    {
        return $this->reflection;
    }

    /**
     * @return class-string<T>
     */
    public function className(): string
    {
        return $this->reflection->getName();
    }

    /**
     * @return array<string, PropertyMetadata>
     */
    public function properties(): array
    {
        return $this->properties;
    }

    /**
     * @return T
     */
    public function newInstance(): object
    {
        return $this->reflection->newInstanceWithoutConstructor();
    }
}
