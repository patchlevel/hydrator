<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

use Patchlevel\Hydrator\ClassHydrator;
use Patchlevel\Hydrator\Metadata\ClassMetadata;

/**
 * A middleware in the chain of a class, it passes the rest of the chain on as its next.
 *
 * @internal
 *
 * @template T of object
 * @implements ClassHydrator<T>
 */
final class MiddlewareHydrator implements ClassHydrator
{
    /**
     * @param ClassMetadata<T> $metadata
     * @param ClassHydrator<T> $next
     */
    public function __construct(
        private readonly Middleware $middleware,
        private readonly ClassMetadata $metadata,
        private readonly ClassHydrator $next,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     */
    public function hydrate(array $data, array $context): object
    {
        return $this->middleware->hydrate($this->metadata, $data, $context, $this->next);
    }
}
