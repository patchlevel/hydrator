<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

use Patchlevel\Hydrator\ClassExtractor;
use Patchlevel\Hydrator\Metadata\ClassMetadata;

/**
 * A middleware in the chain of a class, it passes the rest of the chain on as its next.
 *
 * @internal
 *
 * @template T of object
 * @implements ClassExtractor<T>
 */
final class MiddlewareExtractor implements ClassExtractor
{
    /**
     * @param ClassMetadata<T>  $metadata
     * @param ClassExtractor<T> $next
     */
    public function __construct(
        private readonly Middleware $middleware,
        private readonly ClassMetadata $metadata,
        private readonly ClassExtractor $next,
    ) {
    }

    /**
     * @param T                    $object
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extract(object $object, array $context): array
    {
        return $this->middleware->extract($this->metadata, $object, $context, $this->next);
    }
}
