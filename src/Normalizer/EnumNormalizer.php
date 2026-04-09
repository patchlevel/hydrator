<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use BackedEnum;
use Deprecated;
use ReflectionType;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Throwable;

use function is_int;
use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final class EnumNormalizer implements Normalizer, ReflectionTypeAwareNormalizer, TypeAwareNormalizer
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

        $enum = $this->className();

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

        $enum = $this->className();

        try {
            return $enum::from($value);
        } catch (Throwable $error) {
            throw InvalidArgument::fromThrowable($error);
        }
    }

    /** @deprecated use `handleType()` instead */
    public function handleReflectionType(ReflectionType|null $reflectionType): void
    {
        if ($this->enum !== null || $reflectionType === null) {
            return;
        }

        $this->enum = ReflectionTypeUtil::classStringInstanceOf($reflectionType, BackedEnum::class);
    }

    public function handleType(Type|null $type): void
    {
        if ($this->enum !== null || $type === null) {
            return;
        }

        if ($type instanceof NullableType) {
            $type = $type->getWrappedType();
        }

        if (!$type instanceof BackedEnumType) {
            return;
        }

        $this->enum = $type->getClassName();
    }

    /** @return class-string<BackedEnum> */
    #[Deprecated('Use `className()` method instead')]
    public function getEnum(): string
    {
        return $this->className();
    }

    /** @return class-string<BackedEnum> */
    public function className(): string
    {
        if ($this->enum === null) {
            throw InvalidType::missingType();
        }

        return $this->enum;
    }
}
