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
 *     directAccess: bool,
 * }
 */
final class PropertyMetadata
{
    public readonly string $propertyName;

    private bool $directAccess;

    /** @param array<string, mixed> $extras */
    public function __construct(
        public readonly ReflectionProperty $reflection,
        public string $fieldName,
        public Normalizer|null $normalizer = null,
        public array $extras = [],
    ) {
        $this->propertyName = $reflection->getName();
        $this->directAccess = $reflection->isPublic();
    }

    public function setValue(object $object, mixed $value): void
    {
        if ($this->directAccess) {
            $object->{$this->propertyName} = $value;

            return;
        }

        $this->reflection->setValue($object, $value);
    }

    public function getValue(object $object): mixed
    {
        return $this->directAccess ? $object->{$this->propertyName} : $this->reflection->getValue($object);
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
            'directAccess' => $this->directAccess,
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
        $this->directAccess = $data['directAccess'];
    }
}
