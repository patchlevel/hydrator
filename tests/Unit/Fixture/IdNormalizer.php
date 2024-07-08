<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Attribute;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\ReflectionTypeAwareNormalizer;
use Patchlevel\Hydrator\Normalizer\ReflectionTypeUtil;
use ReflectionType;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final class IdNormalizer implements Normalizer, ReflectionTypeAwareNormalizer
{
    public function __construct(
        /** @var class-string<Id>|null */
        private string|null $idClass = null,
    ) {
    }

    public function normalize(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        $class = $this->idClass();

        if (!$value instanceof Id) {
            throw InvalidArgument::withWrongType($class, $value);
        }

        return $value->toString();
    }

    public function denormalize(mixed $value): Id|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string', $value);
        }

        $class = $this->idClass();

        return $class::fromString($value);
    }

    public function handleReflectionType(ReflectionType|null $reflectionType): void
    {
        if ($this->idClass !== null || $reflectionType === null) {
            return;
        }

        $this->idClass = ReflectionTypeUtil::classStringInstanceOf(
            $reflectionType,
            Id::class,
        );
    }

    /** @return class-string<Id> */
    public function idClass(): string
    {
        if ($this->idClass === null) {
            throw InvalidType::missingType();
        }

        return $this->idClass;
    }
}
