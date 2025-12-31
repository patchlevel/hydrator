<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Hydrator;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\NullableType;

use function is_array;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ArrayShapeNormalizer implements Normalizer, TypeAwareNormalizer, HydratorAwareNormalizer
{
    /** @param array<array-key, Normalizer> $normalizerMap */
    public function __construct(
        private readonly array $normalizerMap,
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

        foreach ($this->normalizerMap as $field => $normalizer) {
            if (!isset($value[$field])) {
                continue;
            }

            $result[$field] = $normalizer->normalize($value[$field], $context);
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

        foreach ($this->normalizerMap as $field => $normalizer) {
            if (!isset($value[$field])) {
                continue;
            }

            $result[$field] = $normalizer->denormalize($value[$field], $context);
        }

        return $result;
    }

    public function setHydrator(Hydrator $hydrator): void
    {
        foreach ($this->normalizerMap as $normalizer) {
            if (!$normalizer instanceof HydratorAwareNormalizer) {
                continue;
            }

            $normalizer->setHydrator($hydrator);
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

        if (!$type instanceof ArrayShapeType) {
            return;
        }

        $shape = $type->getShape();

        foreach ($this->normalizerMap as $field => $normalizer) {
            if (!$normalizer instanceof TypeAwareNormalizer) {
                continue;
            }

            $normalizer->handleType($shape[$field]['type']);
        }
    }
}
