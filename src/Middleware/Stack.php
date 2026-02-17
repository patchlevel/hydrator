<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

final class Stack
{
    /**
     * @param non-empty-list<Middleware> $middlewares
     * @param int                        $index       Position of the middleware returned by the next call to next().
     */
    public function __construct(
        private readonly array $middlewares,
        private int $index = 0,
    ) {
    }

    public function next(): Middleware
    {
        $next = $this->middlewares[$this->index] ?? null;

        if ($next === null) {
            throw new NoMoreMiddleware($this->middlewares);
        }

        $this->index++;

        return $next;
    }
}
