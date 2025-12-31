<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use DateTime;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class DateTimeNormalizer implements Normalizer
{
    public function __construct(
        private readonly string $format = DateTime::ATOM,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function normalize(mixed $value, array $context): string|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTime) {
            throw InvalidArgument::withWrongType('DateTime|null', $value);
        }

        return $value->format($this->format);
    }

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $value, array $context): DateTime|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string|null', $value);
        }

        $date = DateTime::createFromFormat($this->format, $value);

        if ($date === false) {
            throw new InvalidArgument();
        }

        return $date;
    }
}
