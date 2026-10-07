<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Closure;
use Patchlevel\Hydrator\Handler\ExtractHandler;
use Patchlevel\Hydrator\Handler\HydrateHandler;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use Patchlevel\Hydrator\Transformer\Direction;
use Patchlevel\Hydrator\Transformer\TransformerResolver;

/** @internal the resolver the {@see StackHydrator} passes to its transformer factory */
final class StackTransformerResolver implements TransformerResolver
{
    /**
     * @param Closure(class-string): (ClassTransformer|HydrateHandler) $hydrateHandler
     * @param Closure(class-string): (ClassTransformer|ExtractHandler) $extractHandler
     */
    public function __construct(
        private readonly StackHydrator $hydrator,
        private readonly Closure $hydrateHandler,
        private readonly Closure $extractHandler,
    ) {
    }

    public function hydrator(): Hydrator
    {
        return $this->hydrator;
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
        return $this->hydrator->metadata($class);
    }

    /** @param class-string $class */
    public function hydrateHandler(string $class): ClassTransformer|HydrateHandler
    {
        return ($this->hydrateHandler)($class);
    }

    /** @param class-string $class */
    public function extractHandler(string $class): ClassTransformer|ExtractHandler
    {
        return ($this->extractHandler)($class);
    }

    /** @param class-string $class */
    public function direct(string $class, Direction $direction): ClassTransformer|null
    {
        $handler = $direction === Direction::Hydrate
            ? ($this->hydrateHandler)($class)
            : ($this->extractHandler)($class);

        return $handler instanceof ClassTransformer ? $handler : null;
    }
}
