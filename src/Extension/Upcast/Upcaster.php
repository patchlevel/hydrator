<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Upcast;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

interface Upcaster
{
    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     *
     * @template T of object
     */
    public function upcast(ClassMetadata $metadata, array $data, array $context): array;
}
