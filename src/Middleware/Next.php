<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Transformer\ClassTransformer;

use function array_slice;
use function assert;

/**
 * The rest of the middleware stack. A middleware calls it to pass the data on to the next middleware, after the last
 * one the transformer maps the data to the object and back.
 *
 * The stack is an immutable chain which is built once per class and reused for every call, so a middleware can call
 * the rest of the stack more than once.
 */
final class Next
{
    private readonly Middleware|null $middleware;
    private readonly Next|null $next;

    /** @param list<Middleware> $middlewares */
    public function __construct(
        array $middlewares,
        private readonly ClassTransformer $transformer,
    ) {
        $this->middleware = $middlewares[0] ?? null;
        $this->next = $this->middleware === null ? null : new self(array_slice($middlewares, 1), $transformer);
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
        if ($this->middleware === null || $this->next === null) {
            $object = $this->transformer->hydrate($data, $context);
            assert($object instanceof $metadata->className);

            return $object;
        }

        return $this->middleware->hydrate($metadata, $data, $context, $this->next);
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
        if ($this->middleware === null || $this->next === null) {
            return $this->transformer->extract($object, $context);
        }

        return $this->middleware->extract($metadata, $object, $context, $this->next);
    }
}
