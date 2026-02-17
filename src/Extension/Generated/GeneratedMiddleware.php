<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Closure;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;

/**
 * Implemented by the generated middleware. For the classes it handles completely on its own, without any other
 * middleware, it offers closures which the {@see GeneratedHydrator} calls directly, skipping the middleware stack.
 *
 * @internal
 */
interface GeneratedMiddleware extends Middleware
{
    /**
     * @param ClassMetadata<T> $metadata
     *
     * @return (Closure(array<string, mixed>, array<string, mixed>): object)|null null if the class is not handled exclusively by this middleware
     *
     * @template T of object
     */
    public function compiledHydrator(ClassMetadata $metadata): Closure|null;

    /**
     * @param ClassMetadata<T> $metadata
     *
     * @return (Closure(object, array<string, mixed>): array<string, mixed>)|null null if the class is not handled exclusively by this middleware
     *
     * @template T of object
     */
    public function compiledExtractor(ClassMetadata $metadata): Closure|null;
}
