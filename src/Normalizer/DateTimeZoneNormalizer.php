<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use DateTimeZone;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class DateTimeZoneNormalizer implements Normalizer
{
    /** @param array<string, mixed> $context */
    public function normalize(mixed $value, array $context): string|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTimeZone) {
            throw InvalidArgument::withWrongType('DateTimeZone|null', $value);
        }

        return $value->getName();
    }

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $value, array $context): DateTimeZone|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || $value === '') {
            throw InvalidArgument::withWrongType('string|null', $value);
        }

        return new DateTimeZone($value);
    }
}
