<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use ReflectionClass;

final class ClassMetadata
{
    public function __construct(
        private readonly ReflectionClass $reflection,
        /** @var list<PropertyMetadata> */
        private readonly array $properties = [],
    ) {
    }

    public function reflection(): ReflectionClass
    {
        return $this->reflection;
    }

    public function className(): string
    {
        return $this->reflection->getName();
    }

    /**
     * @return list<PropertyMetadata>
     */
    public function properties(): array
    {
        return $this->properties;
    }

    public function newInstance(): object
    {
        return $this->reflection->newInstanceWithoutConstructor();
    }
}
