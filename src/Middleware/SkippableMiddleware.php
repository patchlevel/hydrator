<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

interface SkippableMiddleware extends Middleware
{
    /**
     * Decide for which directions this middleware has nothing to do for the given class.
     *
     * The result is determined once per class and then reused, so the decision
     * must only depend on the metadata.
     *
     * @param ClassMetadata<T> $metadata
     *
     * @template T of object
     */
    public function skip(ClassMetadata $metadata): Skip;
}
