<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Transformer\ClassTransformer;

use function assert;

/**
 * The rest of the middleware stack. A middleware calls it to pass the data on to the next middleware, after the last
 * one the transformer maps the data to the object and back.
 */
final class Next
{
    private int $index = 0;

    /** @param list<Middleware> $middlewares */
    public function __construct(
        private readonly array $middlewares,
        private readonly ClassTransformer $transformer,
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
        $middleware = $this->middlewares[$this->index] ?? null;

        if ($middleware === null) {
            $object = $this->transformer->hydrate($data, $context);
            assert($object instanceof $metadata->className);

            return $object;
        }

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
        $middleware = $this->middlewares[$this->index] ?? null;

        if ($middleware === null) {
            return $this->transformer->extract($object, $context);
        }

        $this->index++;

        try {
            return $middleware->extract($metadata, $object, $context, $this);
        } finally {
            $this->index--;
        }
    }
}
