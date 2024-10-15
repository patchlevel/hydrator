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
     * @throws ClassNotFound if the class does not exist.
     *
     * @template T of object
     */
    public function metadata(string $class): ClassMetadata;
}
