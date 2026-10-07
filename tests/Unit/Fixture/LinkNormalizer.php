<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Attribute;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Normalizer\MissingHydrator;
use Patchlevel\Hydrator\Normalizer\Normalizer;

use function is_array;

/** A custom normalizer which maps the linked object with the hydrator of the context. */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class LinkNormalizer implements Normalizer
{
    /** @param array<string, mixed> $context */
    public function normalize(mixed $value, array $context): mixed
    {
        if (!$value instanceof LinkedDto) {
            return null;
        }

        $hydrator = $context[Hydrator::HYDRATOR] ?? null;

        if (!$hydrator instanceof Hydrator) {
            throw new MissingHydrator();
        }

        return $hydrator->extract($value, $context);
    }

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $value, array $context): mixed
    {
        if (!is_array($value)) {
            return null;
        }

        $hydrator = $context[Hydrator::HYDRATOR] ?? null;

        if (!$hydrator instanceof Hydrator) {
            throw new MissingHydrator();
        }

        return $hydrator->hydrate(LinkedDto::class, $value, $context);
    }
}
