<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;
use Patchlevel\Hydrator\Middleware\Stack;

/**
 * Holds the place of the generated middleware in the stack. The code is generated when the hydrator is built, after
 * all extensions have registered their guessers and enrichers, and then loaded into this slot.
 *
 * @internal
 */
final class GeneratedMiddlewareSlot implements SkippableMiddleware
{
    private GeneratedMiddleware|null $middleware = null;

    public function load(GeneratedMiddleware $middleware): void
    {
        $this->middleware = $middleware;
    }

    public function holds(GeneratedMiddleware $middleware): bool
    {
        return $this->middleware === $middleware;
    }

    public function skip(ClassMetadata $metadata): Skip
    {
        $middleware = $this->middleware();

        return $middleware instanceof SkippableMiddleware ? $middleware->skip($metadata) : Skip::None;
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
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Stack $stack): object
    {
        return $this->middleware()->hydrate($metadata, $data, $context, $stack);
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
    public function extract(ClassMetadata $metadata, object $object, array $context, Stack $stack): array
    {
        return $this->middleware()->extract($metadata, $object, $context, $stack);
    }

    private function middleware(): GeneratedMiddleware
    {
        return $this->middleware ?? throw new GeneratedMiddlewareNotLoaded();
    }
}
