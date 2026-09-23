<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

use function sprintf;

final class AllMiddlewaresSkipped extends RuntimeException implements HydratorException
{
    /** @param class-string $className */
    public function __construct(string $className)
    {
        parent::__construct(
            sprintf('All middlewares were skipped for the class "%s", at least one middleware must run.', $className),
        );
    }
}
