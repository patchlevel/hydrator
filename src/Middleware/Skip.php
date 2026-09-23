<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

enum Skip
{
    /** The middleware runs in both directions. */
    case None;

    /** The middleware is left out while hydrating, but runs while extracting. */
    case Hydrate;

    /** The middleware is left out while extracting, but runs while hydrating. */
    case Extract;

    /** The middleware is left out in both directions. */
    case Both;
}
