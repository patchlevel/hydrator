<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use DateTimeImmutable;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class DateTimeImmutableNormalizer implements Normalizer
{
    /** Context key to override the format, e.g. with the Context attribute. */
    public const FORMAT = 'datetime_format';

    public function __construct(
        private string $format = DateTimeImmutable::ATOM,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function normalize(mixed $value, array $context): string|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTimeImmutable) {
            throw InvalidArgument::withWrongType('DateTimeImmutable|null', $value);
        }

        return $value->format($this->format($context));
    }

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $value, array $context): DateTimeImmutable|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string|null', $value);
        }

        $date = DateTimeImmutable::createFromFormat($this->format($context), $value);

        if ($date === false) {
            throw new InvalidArgument();
        }

        return $date;
    }

    /** @param array<string, mixed> $context */
    private function format(array $context): string
    {
        $format = $context[self::FORMAT] ?? null;

        return is_string($format) ? $format : $this->format;
    }
}
