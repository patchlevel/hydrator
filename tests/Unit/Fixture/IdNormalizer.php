<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Attribute;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\TypeAwareNormalizer;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final class IdNormalizer implements Normalizer, TypeAwareNormalizer
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

    public function handleType(Type|null $type): void
    {
        if ($this->idClass !== null || $type === null) {
            return;
        }

        if ($type instanceof NullableType) {
            $type = $type->getWrappedType();
        }

        if (!$type instanceof ObjectType) {
            throw InvalidType::unsupportedType(
                new ObjectType(Id::class),
                $type,
            );
        }

        $this->idClass = $type->getClassName();
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
