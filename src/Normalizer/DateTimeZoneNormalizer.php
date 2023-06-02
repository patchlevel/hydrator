<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use DateTimeZone;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class DateTimeZoneNormalizer implements Normalizer
{
    public function normalize(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTimeZone) {
            throw InvalidArgument::withWrongType('\DateTimeZone', $value);
        }

        return $value->getName();
    }

    public function denormalize(mixed $value): DateTimeZone|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string', $value);
        }

        return new DateTimeZone($value);
    }
}
