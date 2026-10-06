<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Tracing;

use Closure;

interface Tracer
{
    /**
     * Runs the hydrate or extract call and measures it. The result of the callback has to be returned unchanged
     * and exceptions have to be passed on.
     *
     * @param class-string $class
     * @param Closure(): T $callback
     *
     * @return T
     *
     * @template T
     */
    public function trace(Operation $operation, string $class, Closure $callback): mixed;
}
