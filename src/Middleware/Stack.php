<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

/** @experimental */
final class Stack
{
    private int $index = 0;

    /** @param non-empty-list<Middleware> $middlewares */
    public function __construct(
        private readonly array $middlewares,
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
