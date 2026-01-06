<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Guesser\BuiltInGuesser;
use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\TransformMiddleware;

final class CoreExtension implements MiddlewareProvider, GuesserProvider
{
    /** @return iterable<Middleware|array{0: Middleware, 1?: int}> */
    public function middlewares(): iterable
    {
        yield new TransformMiddleware();
    }

    /** @return iterable<Guesser|array{0: Guesser, 1?: int}> */
    public function guesser(): iterable
    {
        yield new BuiltInGuesser();
    }
}
