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
 *     subjectIdName: string|null,
 *     sensitiveDataSubjectIdName: string|null,
 *     sensitiveDataFallback: mixed
 * }
 */
final class PropertyMetadata
{
    private const ENCRYPTED_PREFIX = '!';

    /** @param (callable(string, mixed):mixed)|null $sensitiveDataFallbackCallable */
    public function __construct(
        public readonly ReflectionProperty $reflection,
        public readonly string $fieldName,
        public readonly Normalizer|null $normalizer = null,
        public readonly string|null $subjectIdName = null,
        public readonly string|null $sensitiveDataSubjectIdName = null,
        public readonly mixed $sensitiveDataFallback = null,
        public readonly mixed $sensitiveDataFallbackCallable = null,
    ) {
        if (str_starts_with($fieldName, self::ENCRYPTED_PREFIX)) {
            throw new InvalidArgumentException('fieldName must not start with !');
        }
    }

    public function propertyName(): string
    {
        return $this->reflection->getName();
    }

    public function encryptedFieldName(): string
    {
        return self::ENCRYPTED_PREFIX . $this->fieldName;
    }

    public function setValue(object $object, mixed $value): void
    {
        $this->reflection->setValue($object, $value);
    }

    public function getValue(object $object): mixed
    {
        return $this->reflection->getValue($object);
    }

    /** @phpstan-assert-if-true !null $this->sensitiveDataSubjectIdName */
    public function isSensitiveData(): bool
    {
        return $this->sensitiveDataSubjectIdName !== null;
    }

    /** @phpstan-assert-if-true !null $this->subjectIdName */
    public function isSubjectId(): bool
    {
        return $this->subjectIdName !== null;
    }

    /** @return (Closure(string, mixed):mixed)|null */
    public function sensitiveDataFallbackCallable(): Closure|null
    {
        if ($this->sensitiveDataFallbackCallable) {
            return ($this->sensitiveDataFallbackCallable)(...);
        }

        return null;
    }

    /** @return serialized */
    public function __serialize(): array
    {
        return [
            'className' => $this->reflection->getDeclaringClass()->getName(),
            'property' => $this->reflection->getName(),
            'fieldName' => $this->fieldName,
            'normalizer' => $this->normalizer,
            'subjectIdName' => $this->subjectIdName,
            'sensitiveDataSubjectIdName' => $this->sensitiveDataSubjectIdName,
            'sensitiveDataFallback' => $this->sensitiveDataFallback,
        ];
    }

    /** @param serialized $data */
    public function __unserialize(array $data): void
    {
        $this->reflection = new ReflectionProperty($data['className'], $data['property']);
        $this->fieldName = $data['fieldName'];
        $this->normalizer = $data['normalizer'];
        $this->subjectIdName = $data['subjectIdName'];
        $this->sensitiveDataSubjectIdName = $data['sensitiveDataSubjectIdName'];
        $this->sensitiveDataFallback = $data['sensitiveDataFallback'];
    }
}
