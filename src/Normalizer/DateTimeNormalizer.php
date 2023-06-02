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

    public function normalize(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTime) {
            throw InvalidArgument::withWrongType('\DateTime', $value);
        }

        return $value->format($this->format);
    }

    public function denormalize(mixed $value): DateTime|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string', $value);
        }

        $date = DateTime::createFromFormat($this->format, $value);

        if ($date === false) {
            throw new InvalidArgument();
        }

        return $date;
    }
}
