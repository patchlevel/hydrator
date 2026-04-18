<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark\Fixture;

use Attribute;
use InvalidArgumentException;
use Patchlevel\Hydrator\Normalizer\Normalizer;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ProfileIdNormalizer implements Normalizer
{
    /** @param array<string, mixed> $context */
    public function normalize(mixed $value, array $context): string
    {
        if (!$value instanceof ProfileId) {
            throw new InvalidArgumentException();
        }

        return $value->toString();
    }

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $value, array $context): ProfileId|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException();
        }

        return ProfileId::fromString($value);
    }
}
