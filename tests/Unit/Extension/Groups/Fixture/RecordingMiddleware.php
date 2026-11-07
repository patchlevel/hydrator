<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;

final class RecordingMiddleware implements Middleware
{
    public ClassMetadata|null $metadata = null;

    /** @var array<string, mixed> */
    public array $data = [];

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
        $this->metadata = $metadata;
        $this->data = $data;

        return $metadata->newInstance();
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extract(ClassMetadata $metadata, object $object, array $context, Stack $stack): array
    {
        $this->metadata = $metadata;

        return [];
    }
}
