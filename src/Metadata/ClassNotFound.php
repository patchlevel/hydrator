<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use RuntimeException;

use function sprintf;

final class ClassNotFound extends RuntimeException implements MetadataException
{
    /** @param class-string $className */
    public function __construct(string $className)
    {
        parent::__construct(sprintf('Class %s not found', $className));
    }
}
