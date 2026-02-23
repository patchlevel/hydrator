<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use Patchlevel\Hydrator\Normalizer\Normalizer;
use ReflectionProperty;

/**
 * @phpstan-type serialized = array{
 *     className: class-string,
 *     propertyName: string,
 *     fieldName: string,
 *     normalizer: Normalizer|null,
 *     extras: array<string, mixed>,
 * }
 */
final class PropertyMetadata
{
    public readonly string $propertyName;

    /** @param array<string, mixed> $extras */
    public function __construct(
        public readonly ReflectionProperty $reflection,
        public string $fieldName,
        public Normalizer|null $normalizer = null,
        public array $extras = [],
    ) {
        $this->propertyName = $reflection->getName();
    }

    public function setValue(object $object, mixed $value): void
    {
        $this->reflection->setValue($object, $value);
    }

    public function getValue(object $object): mixed
    {
        return $this->reflection->getValue($object);
    }

    /** @return serialized */
    public function __serialize(): array
    {
        return [
            'className' => $this->reflection->getDeclaringClass()->getName(),
            'propertyName' => $this->propertyName,
            'fieldName' => $this->fieldName,
            'normalizer' => $this->normalizer,
            'extras' => $this->extras,
        ];
    }

    /** @param serialized $data */
    public function __unserialize(array $data): void
    {
        $this->reflection = new ReflectionProperty($data['className'], $data['propertyName']);
        $this->propertyName = $data['propertyName'];
        $this->fieldName = $data['fieldName'];
        $this->normalizer = $data['normalizer'];
        $this->extras = $data['extras'];
    }
}
