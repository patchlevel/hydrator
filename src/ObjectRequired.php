<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Normalizer\Normalizer;
use RuntimeException;

use function sprintf;

final class ObjectRequired extends RuntimeException implements HydratorException
{
    /**
     * @param class-string             $class
     * @param class-string<Normalizer> $normalizerClass
     */
    public function __construct(
        string $class,
        string $normalizerClass,
    ) {
        parent::__construct(
            sprintf(
                'The result of the normalizer "%s" for the class "%s" must be an instance of "%s".',
                $normalizerClass,
                $class,
                $class,
            ),
        );
    }
}
