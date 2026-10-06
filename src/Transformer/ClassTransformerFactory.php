<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\StackHydrator;

/**
 * Provides an alternative transformer for a class, for example generated code. Factories are registered with
 * {@see \Patchlevel\Hydrator\StackHydratorBuilder::addTransformerFactory()}, the first one which returns a transformer
 * wins. Classes no factory is responsible for are transformed with reflection.
 */
interface ClassTransformerFactory
{
    /**
     * Called once per class and hydrator, the transformer is cached by the hydrator.
     *
     * @param ClassMetadata<T> $metadata
     *
     * @template T of object
     */
    public function create(ClassMetadata $metadata, StackHydrator $hydrator): ClassTransformer|null;
}
