<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use ArrayObject;
use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;

final class RecordingMiddleware implements Middleware
{
    /** @param ArrayObject<int, string> $calls */
    public function __construct(
        private readonly string $name,
        private readonly ArrayObject $calls,
    ) {
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     * @param Mapper<T>            $next
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Mapper $next): object
    {
        $this->calls[] = 'hydrate ' . $this->name;

        return $next->hydrate($data, $context);
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param T                    $object
     * @param array<string, mixed> $context
     * @param Mapper<T>            $next
     *
     * @return array<string, mixed>
     *
     * @template T of object
     */
    public function extract(ClassMetadata $metadata, object $object, array $context, Mapper $next): array
    {
        $this->calls[] = 'extract ' . $this->name;

        return $next->extract($object, $context);
    }
}
