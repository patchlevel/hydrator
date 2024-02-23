<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use DateTimeImmutable;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class DateTimeImmutableNormalizer implements Normalizer
{
    public function __construct(
        private readonly string $format = DateTimeImmutable::ATOM,
    ) {
    }

    public function normalize(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTimeImmutable) {
            throw InvalidArgument::withWrongType('DateTimeImmutable|null', $value);
        }

        return $value->format($this->format);
    }

    public function denormalize(mixed $value): DateTimeImmutable|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string|null', $value);
        }

        $date = DateTimeImmutable::createFromFormat($this->format, $value);

        if ($date === false) {
            throw new InvalidArgument();
        }

        return $date;
    }
}
