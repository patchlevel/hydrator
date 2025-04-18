<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Hydrator;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\NullableType;

use function array_map;
use function is_array;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ArrayNormalizer implements Normalizer, TypeAwareNormalizer, HydratorAwareNormalizer
{
    public function __construct(
        private readonly Normalizer $normalizer,
    ) {
    }

    /** @return array<array-key, mixed>|null */
    public function normalize(mixed $value): array|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw InvalidArgument::withWrongType('array|null', $value);
        }

        return array_map(fn (mixed $value): mixed => $this->normalizer->normalize($value), $value);
    }

    /** @return array<array-key, mixed>|null */
    public function denormalize(mixed $value): array|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw InvalidArgument::withWrongType('array|null', $value);
        }

        return array_map(fn (mixed $value): mixed => $this->normalizer->denormalize($value), $value);
    }

    public function setHydrator(Hydrator $hydrator): void
    {
        if (!$this->normalizer instanceof HydratorAwareNormalizer) {
            return;
        }

        $this->normalizer->setHydrator($hydrator);
    }

    public function handleType(Type|null $type): void
    {
        if ($type === null) {
            return;
        }

        if ($type instanceof NullableType) {
            $type = $type->getWrappedType();
        }

        if (!$type instanceof CollectionType || !$this->normalizer instanceof TypeAwareNormalizer) {
            return;
        }

        $this->normalizer->handleType($type->getCollectionValueType());
    }
}
