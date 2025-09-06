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
        private readonly ReflectionProperty $reflection,
        private readonly string $fieldName,
        private readonly Normalizer|null $normalizer = null,
        private readonly string|null $subjectIdName = null,
        private readonly string|null $sensitiveDataSubjectIdName = null,
        private readonly mixed $sensitiveDataFallback = null,
        private readonly mixed $sensitiveDataFallbackCallable = null,
    ) {
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
        return $this->reflection->getName();
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

    /** @phpstan-assert-if-true !null $this->sensitiveDataSubjectIdName() */
    public function isSensitiveData(): bool
    {
        return $this->sensitiveDataSubjectIdName !== null;
    }

    /** @phpstan-assert-if-true !null $this->subjectIdName() */
    public function isSubjectId(): bool
    {
        return $this->subjectIdName !== null;
    }

    public function subjectIdName(): string|null
    {
        return $this->subjectIdName;
    }

    public function sensitiveDataFallback(): mixed
    {
        return $this->sensitiveDataFallback;
    }

    /** @return (Closure(string, mixed):mixed)|null */
    public function sensitiveDataFallbackCallable(): Closure|null
    {
        if ($this->sensitiveDataFallbackCallable) {
            return ($this->sensitiveDataFallbackCallable)(...);
        }

        return null;
    }

    public function sensitiveDataSubjectIdName(): string|null
    {
        return $this->sensitiveDataSubjectIdName;
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
