<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

interface MetadataFactory
{
    /**
     * @param class-string $class
     */
    public function metadata(string $class): ClassMetadata;
}
