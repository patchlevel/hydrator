<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use DateInterval;
use Throwable;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class DateIntervalNormalizer implements Normalizer
{
    private const DEFAULT_FORMAT = 'P%YY%MM%DDT%HH%IM%SS';

    public function __construct(private string $format = self::DEFAULT_FORMAT)
    {
    }

    public function normalize(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateInterval) {
            throw InvalidArgument::withWrongType('DateInterval|null', $value);
        }

        return $value->format($this->format);
    }

    public function denormalize(mixed $value): DateInterval|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string|null', $value);
        }

        try {
            $interval = new DateInterval($value);
        } catch (Throwable $e) { // Exception in PHP <= 8.2 or DateMalformedIntervalStringException in 8.3+
            throw new InvalidArgument('Invalid serialized date interval string', 0, $e);
        }

        return $interval;
    }
}
