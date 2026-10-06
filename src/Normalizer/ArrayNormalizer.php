<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\NullableType;

use function is_array;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ArrayNormalizer implements Normalizer, TypeAwareNormalizer
{
    public function __construct(
        private Normalizer $normalizer,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<array-key, mixed>|null
     */
    public function normalize(mixed $value, array $context): array|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw InvalidArgument::withWrongType('array|null', $value);
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[$key] = $this->normalizer->normalize($item, $context);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<array-key, mixed>|null
     */
    public function denormalize(mixed $value, array $context): array|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw InvalidArgument::withWrongType('array|null', $value);
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[$key] = $this->normalizer->denormalize($item, $context);
        }

        return $result;
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

    public function innerNormalizer(): Normalizer
    {
        return $this->normalizer;
    }
}
