<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

/**
 * Provides the transformer of a class. Factories are registered with
 * {@see \Patchlevel\Hydrator\StackHydratorBuilder::addTransformerFactory()}, the first one which returns a transformer
 * wins and the {@see ReflectionTransformerFactory} is always the last one.
 */
interface ClassTransformerFactory
{
    /**
     * Called once per class and hydrator, the transformer is cached by the hydrator.
     *
     * @param ClassMetadata<T>    $metadata
     * @param TransformerResolver $resolver of the hydrator which asks, keep it to map nested objects in place
     *
     * @return ClassTransformer|null null if this factory is not responsible for the class
     *
     * @template T of object
     */
    public function create(ClassMetadata $metadata, TransformerResolver $resolver): ClassTransformer|null;
}
