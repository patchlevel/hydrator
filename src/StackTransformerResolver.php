<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Closure;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use Patchlevel\Hydrator\Transformer\Direction;
use Patchlevel\Hydrator\Transformer\TransformerResolver;

/** @internal the resolver the {@see StackHydrator} passes to its transformer factory */
final class StackTransformerResolver implements TransformerResolver
{
    /** @param Closure(class-string, Direction): (ClassTransformer|false) $direct */
    public function __construct(
        private readonly StackHydrator $hydrator,
        private readonly Closure $direct,
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
    public function direct(string $class, Direction $direction): ClassTransformer|null
    {
        return ($this->direct)($class, $direction) ?: null;
    }
}
