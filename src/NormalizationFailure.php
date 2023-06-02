<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use RuntimeException;
use Throwable;

use function sprintf;

final class NormalizationFailure extends RuntimeException implements HydratorException
{
    /**
     * @param class-string $class
     * @param class-string $normalizer
     */
    public function __construct(string $class, string $property, string $normalizer, Throwable $e)
    {
        parent::__construct(
            sprintf(
                'normalization for the property "%s" in the class "%s" with the normalizer "%s" failed.',
                $property,
                $class,
                $normalizer,
            ),
            0,
            $e,
        );
    }
}
