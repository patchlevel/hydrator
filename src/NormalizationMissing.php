<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use RuntimeException;

use function sprintf;

final class NormalizationMissing extends RuntimeException implements HydratorException
{
    /** @param class-string $class */
    public function __construct(string $class, string $property)
    {
        parent::__construct(
            sprintf(
                'normalization for the property "%s" in the class "%s" is missing. Please define a normalizer so the object can be normalized.',
                $property,
                $class,
            ),
        );
    }
}
