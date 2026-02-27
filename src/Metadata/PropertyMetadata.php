<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use Closure;
use InvalidArgumentException;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use ReflectionProperty;

use function str_starts_with;

/**
 * @psalm-type serialized = array{
 *     className: class-string,
 *     property: string,
 *     fieldName: string,
 *     normalizer: Normalizer|null,
 *     isPersonalData: bool,
 *     personalDataFallback: mixed,
 *     extras: array<string, mixed>
 * }
 */
final class PropertyMetadata
{
    private const ENCRYPTED_PREFIX = '!';

    public readonly string $propertyName;

    /**
     * @param (callable(string, mixed):mixed)|null $personalDataFallbackCallable
     * @param array<string, mixed>                 $extras
     */
    public function __construct(
        public readonly ReflectionProperty $reflection,
        public string $fieldName,
        public Normalizer|null $normalizer = null,
        public readonly bool $isPersonalData = false,
        public readonly mixed $personalDataFallback = null,
        public readonly mixed $personalDataFallbackCallable = null,
        public array $extras = [],
    ) {
        $this->propertyName = $reflection->getName();

        if (str_starts_with($fieldName, self::ENCRYPTED_PREFIX)) {
            throw new InvalidArgumentException('fieldName must not start with !');
        }
    }

    public function reflection(): ReflectionProperty
    {
        return $this->reflection;
    }

    public function propertyName(): string
    {
        return $this->propertyName;
    }

    public function fieldName(): string
    {
        return $this->fieldName;
    }

    public function encryptedFieldName(): string
    {
        return self::ENCRYPTED_PREFIX . $this->fieldName;
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

    public function isPersonalData(): bool
    {
        return $this->isPersonalData;
    }

    public function personalDataFallback(): mixed
    {
        return $this->personalDataFallback;
    }

    /** @return (Closure(string, mixed):mixed)|null */
    public function personalDataFallbackCallback(): Closure|null
    {
        if ($this->personalDataFallbackCallable) {
            return ($this->personalDataFallbackCallable)(...);
        }

        return null;
    }

    /** @return serialized */
    public function __serialize(): array
    {
        return [
            'className' => $this->reflection->getDeclaringClass()->getName(),
            'property' => $this->propertyName,
            'fieldName' => $this->fieldName,
            'normalizer' => $this->normalizer,
            'isPersonalData' => $this->isPersonalData,
            'personalDataFallback' => $this->personalDataFallback,
            'extras' => $this->extras,
        ];
    }

    /** @param serialized $data */
    public function __unserialize(array $data): void
    {
        $this->reflection = new ReflectionProperty($data['className'], $data['property']);
        $this->fieldName = $data['fieldName'];
        $this->normalizer = $data['normalizer'];
        $this->isPersonalData = $data['isPersonalData'];
        $this->personalDataFallback = $data['personalDataFallback'];
        $this->extras = $data['extras'];
    }
}
