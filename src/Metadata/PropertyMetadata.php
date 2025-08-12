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
 *     subjectIdIdentifier: string|null,
 *     personalDataIdentifier: string|null,
 *     personalDataFallback: mixed
 * }
 */
final class PropertyMetadata
{
    private const ENCRYPTED_PREFIX = '!';

    /** @param (callable(string, mixed):mixed)|null $personalDataFallbackCallable */
    public function __construct(
        private readonly ReflectionProperty $reflection,
        private readonly string $fieldName,
        private readonly Normalizer|null $normalizer = null,
        private readonly string|null $subjectIdIdentifier = null,
        private readonly string|null $personalDataIdentifier = null,
        private readonly mixed $personalDataFallback = null,
        private readonly mixed $personalDataFallbackCallable = null,
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

    /** @phpstan-assert-if-true !null $this->personalDataIdentifier() */
    public function isPersonalData(): bool
    {
        return $this->personalDataIdentifier !== null;
    }

    /** @phpstan-assert-if-true !null $this->subjectIdIdentifier() */
    public function isSubjectId(): bool
    {
        return $this->subjectIdIdentifier !== null;
    }

    public function subjectIdIdentifier(): string|null
    {
        return $this->subjectIdIdentifier;
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

    public function personalDataIdentifier(): string|null
    {
        return $this->personalDataIdentifier;
    }

    /** @return serialized */
    public function __serialize(): array
    {
        return [
            'className' => $this->reflection->getDeclaringClass()->getName(),
            'property' => $this->reflection->getName(),
            'fieldName' => $this->fieldName,
            'normalizer' => $this->normalizer,
            'subjectIdIdentifier' => $this->subjectIdIdentifier,
            'personalDataIdentifier' => $this->personalDataIdentifier,
            'personalDataFallback' => $this->personalDataFallback,
        ];
    }

    /** @param serialized $data */
    public function __unserialize(array $data): void
    {
        $this->reflection = new ReflectionProperty($data['className'], $data['property']);
        $this->fieldName = $data['fieldName'];
        $this->normalizer = $data['normalizer'];
        $this->subjectIdIdentifier = $data['subjectIdIdentifier'];
        $this->personalDataIdentifier = $data['personalDataIdentifier'];
        $this->personalDataFallback = $data['personalDataFallback'];
    }
}
