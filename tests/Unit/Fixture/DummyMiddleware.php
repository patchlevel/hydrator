<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\ClassExtractor;
use Patchlevel\Hydrator\ClassHydrator;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;

final class DummyMiddleware implements Middleware
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
    public function hydrate(ClassMetadata $metadata, array $data, array $context, ClassHydrator $next): object
    {
        return $next->hydrate($data, $context);
    }

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
    public function extract(ClassMetadata $metadata, object $object, array $context, ClassExtractor $next): array
    {
        return $next->extract($object, $context);
    }
}
