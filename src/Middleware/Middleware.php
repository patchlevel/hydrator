<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

use Patchlevel\Hydrator\ClassExtractor;
use Patchlevel\Hydrator\ClassHydrator;
use Patchlevel\Hydrator\Metadata\ClassMetadata;

/**
 * Wraps the hydration and extraction of a class. $next is the rest of the chain: the following middlewares and the
 * transformer at the end. It is built once per class and can be called any number of times.
 */
interface Middleware
{
    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     * @param ClassHydrator<T>     $next
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(ClassMetadata $metadata, array $data, array $context, ClassHydrator $next): object;

    /**
     * @param ClassMetadata<T>     $metadata
     * @param T                    $object
     * @param array<string, mixed> $context
     * @param ClassExtractor<T>    $next
     *
     * @return array<string, mixed>
     *
     * @template T of object
     */
    public function extract(ClassMetadata $metadata, object $object, array $context, ClassExtractor $next): array;
}
