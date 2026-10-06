<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

/**
 * The rest of the middleware stack. A middleware calls it to pass the data on to the next middleware.
 */
final class Next
{
    private int $index = 0;

    /** @param non-empty-list<Middleware> $middlewares */
    public function __construct(
        private readonly array $middlewares,
    ) {
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(ClassMetadata $metadata, array $data, array $context): object
    {
        $middleware = $this->middlewares[$this->index] ?? throw new NoMoreMiddleware($this->middlewares);
        $this->index++;

        try {
            return $middleware->hydrate($metadata, $data, $context, $this);
        } finally {
            // the position is restored, so a middleware can call the rest of the stack more than once
            $this->index--;
        }
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param T                    $object
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     *
     * @template T of object
     */
    public function extract(ClassMetadata $metadata, object $object, array $context): array
    {
        $middleware = $this->middlewares[$this->index] ?? throw new NoMoreMiddleware($this->middlewares);
        $this->index++;

        try {
            return $middleware->extract($metadata, $object, $context, $this);
        } finally {
            $this->index--;
        }
    }
}
