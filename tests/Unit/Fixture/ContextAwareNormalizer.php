<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Attribute;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\NormalizerWithContext;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ContextAwareNormalizer implements NormalizerWithContext
{
    /** @param array<string, mixed> $context */
    public function normalize(mixed $value, array $context = []): mixed
    {
        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string', $value);
        }

        $prefix = isset($context['prefix']) && is_string($context['prefix'])
            ? $context['prefix']
            : '';

        return $prefix . $value;
    }

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $value, array $context = []): mixed
    {
        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string', $value);
        }

        $suffix = isset($context['suffix']) && is_string($context['suffix'])
            ? $context['suffix']
            : '';

        return $value . $suffix;
    }
}
