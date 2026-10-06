<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

/** Transforms every class with reflection, it is the fallback after all other factories. */
final class ReflectionTransformerFactory implements ClassTransformerFactory
{
    private readonly CallStack $callStack;

    public function __construct()
    {
        $this->callStack = new CallStack();
    }

    /**
     * @param ClassMetadata<T> $metadata
     *
     * @return ReflectionTransformer<T>
     *
     * @template T of object
     */
    public function create(ClassMetadata $metadata): ReflectionTransformer
    {
        return new ReflectionTransformer($metadata, $this->callStack);
    }
}
