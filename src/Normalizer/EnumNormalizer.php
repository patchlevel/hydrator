<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use BackedEnum;
use ReflectionType;
use Throwable;

use function is_int;
use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final class EnumNormalizer implements Normalizer, ReflectionTypeAwareNormalizer
{
    /** @param class-string<BackedEnum>|null $enum */
    public function __construct(
        private string|null $enum = null,
    ) {
    }

    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $enum = $this->getEnum();

        if (!$value instanceof $enum) {
            throw InvalidArgument::withWrongType($enum . '|null', $value);
        }

        return $value->value;
    }

    public function denormalize(mixed $value): BackedEnum|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) && !is_int($value)) {
            throw InvalidArgument::withWrongType('string|int|null', $value);
        }

        $enum = $this->getEnum();

        try {
            return $enum::from($value);
        } catch (Throwable $error) {
            throw InvalidArgument::fromThrowable($error);
        }
    }

    public function handleReflectionType(ReflectionType|null $reflectionType): void
    {
        if ($this->enum !== null || $reflectionType === null) {
            return;
        }

        $this->enum = ReflectionTypeUtil::classStringInstanceOf($reflectionType, BackedEnum::class);
    }

    /** @return class-string<BackedEnum> */
    public function getEnum(): string
    {
        if ($this->enum === null) {
            throw InvalidType::missingType();
        }

        return $this->enum;
    }
}
