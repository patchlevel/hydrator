<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Metadata\ClassMetadata;

/**
 * A middleware in the mapper chain of a class. It passes the rest of the chain to the middleware, in a direction the
 * middleware skips it calls the rest of the chain directly.
 *
 * @internal
 *
 * @template T of object
 * @implements Mapper<T>
 */
final class MiddlewareMapper implements Mapper
{
    private readonly bool $hydrate;

    private readonly bool $extract;

    /**
     * @param ClassMetadata<T> $metadata
     * @param Mapper<T>        $next
     */
    public function __construct(
        private readonly Middleware $middleware,
        private readonly ClassMetadata $metadata,
        private readonly Mapper $next,
        Skip $skip = Skip::None,
    ) {
        $this->hydrate = $skip !== Skip::Hydrate && $skip !== Skip::Both;
        $this->extract = $skip !== Skip::Extract && $skip !== Skip::Both;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     */
    public function hydrate(array $data, array $context): object
    {
        if (!$this->hydrate) {
            return $this->next->hydrate($data, $context);
        }

        return $this->middleware->hydrate($this->metadata, $data, $context, $this->next);
    }

    /**
     * @param T                    $object
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extract(object $object, array $context): array
    {
        if (!$this->extract) {
            return $this->next->extract($object, $context);
        }

        return $this->middleware->extract($this->metadata, $object, $context, $this->next);
    }
}
