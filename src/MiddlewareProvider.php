<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Middleware\Middleware;

interface MiddlewareProvider
{
    /** @return iterable<Middleware|array{0: Middleware, 1?: int}> */
    public function middlewares(): iterable;
}
