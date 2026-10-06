<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\StackHydrator;

/** Transforms every class with reflection, it is the fallback after all other factories. */
final class ReflectionTransformerFactory implements ClassTransformerFactory
{
    /**
     * @param ClassMetadata<T> $metadata
     *
     * @return ReflectionTransformer<T>
     *
     * @template T of object
     */
    public function create(ClassMetadata $metadata, StackHydrator $hydrator): ReflectionTransformer
    {
        return new ReflectionTransformer($metadata);
    }
}
