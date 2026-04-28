<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

use function array_map;
use function count;
use function implode;
use function sprintf;

final class NoMoreMiddleware extends RuntimeException implements HydratorException
{
    /** @param non-empty-list<Middleware> $middlewares */
    public function __construct(array $middlewares)
    {
        parent::__construct(
            sprintf(
                'The next middleware in %s was requested, but no further middleware exists. The following middlewares were executed: %s',
                $middlewares[count($middlewares) - 1]::class,
                implode(', ', array_map(static fn (Middleware $middleware): string => $middleware::class, $middlewares)),
            ),
        );
    }
}
