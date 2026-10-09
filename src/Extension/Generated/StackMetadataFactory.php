<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\StackHydrator;

/**
 * Generates the code from the metadata of the stack hydrator, so the fingerprints match the metadata at runtime.
 *
 * @internal
 */
final class StackMetadataFactory implements MetadataFactory
{
    public function __construct(
        private readonly StackHydrator $stack,
    ) {
    }

    /**
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     *
     * @template T of object
     */
    public function metadata(string $class): ClassMetadata
    {
        return $this->stack->metadata($class);
    }
}
