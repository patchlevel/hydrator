<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

interface MetadataFactory
{
    /**
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     *
     * @template T of object
     */
    public function metadata(string $class): ClassMetadata;
}
