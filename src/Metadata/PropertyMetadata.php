<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use Patchlevel\Hydrator\Normalizer\Normalizer;
use ReflectionProperty;

final class PropertyMetadata
{
    public function __construct(
        private readonly ReflectionProperty $reflection,
        private readonly string $fieldName,
        private readonly Normalizer|null $normalizer = null,
    ) {
    }

    public function reflection(): ReflectionProperty
    {
        return $this->reflection;
    }

    public function propertyName(): string
    {
        return $this->reflection->getName();
    }

    public function fieldName(): string
    {
        return $this->fieldName;
    }

    public function normalizer(): Normalizer|null
    {
        return $this->normalizer;
    }

    public function setValue(object $object, mixed $value): void
    {
        $this->reflection->setValue($object, $value);
    }

    public function getValue(object $object): mixed
    {
        return $this->reflection->getValue($object);
    }

    /** @return array{className: class-string, property: string, fieldName: string, normalizer: Normalizer|null} */
    public function __serialize(): array
    {
        return [
            'className' => $this->reflection->getDeclaringClass()->getName(),
            'property' => $this->reflection->getName(),
            'fieldName' => $this->fieldName,
            'normalizer' => $this->normalizer,
        ];
    }

    /** @param array{className: class-string, property: string, fieldName: string, normalizer: Normalizer|null} $data */
    public function __unserialize(array $data): void
    {
        $this->reflection = new ReflectionProperty($data['className'], $data['property']);
        $this->fieldName = $data['fieldName'];
        $this->normalizer = $data['normalizer'];
    }
}
