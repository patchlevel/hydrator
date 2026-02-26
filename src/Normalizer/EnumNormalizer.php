<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use BackedEnum;
use RuntimeException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Throwable;

use function is_int;
use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final class EnumNormalizer implements Normalizer, TypeAwareNormalizer
{
    /** @param class-string<BackedEnum>|null $enum */
    public function __construct(
        private string|null $enum = null,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function normalize(mixed $value, array $context): mixed
    {
        if ($value === null) {
            return null;
        }

        return $value->value;
    }

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $value, array $context): BackedEnum|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) && !is_int($value)) {
            throw InvalidArgument::withWrongType('string|int|null', $value);
        }

        if ($this->enum === null) {
            throw InvalidType::missingType();
        }

        try {
            return $this->enum::from($value);
        } catch (Throwable $error) {
            throw InvalidArgument::fromThrowable($error);
        }
    }

    public function handleType(Type|null $type): void
    {
        if ($type === null) {
            return;
        }

        if ($type instanceof NullableType) {
            $type = $type->getWrappedType();
        }

        if (!$type instanceof BackedEnumType) {
            throw new RuntimeException();
        }

        if ($this->enum === null) {
            $this->enum = $type->getClassName();

            return;
        }

        if (!$type->isIdentifiedBy($this->enum)) {
            throw new RuntimeException();
        }
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
