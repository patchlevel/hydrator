<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

final class Stack
{
    private int $index = 0;

    /** @param list<Middleware> $middlewares */
    public function __construct(
        private readonly array $middlewares,
    ) {
    }

    public function next(): Middleware
    {
        return $this->middlewares[$this->index++] ?? throw new NoMoreMiddleware();
    }
}
