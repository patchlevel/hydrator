<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

use Patchlevel\Hydrator\StackHydrator;

/**
 * @internal
 *
 * Middlewares implementing this interface receive the hydrator they are part of when the hydrator is built.
 */
interface HydratorAwareMiddleware
{
    public function setHydrator(StackHydrator $hydrator): void;
}
