<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Closure;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Transformer\TransformerResolver;

/** @internal the resolver the {@see StackHydrator} passes to its transformer factory */
final class StackTransformerResolver implements TransformerResolver
{
    /**
     * @param Closure(class-string): (ClassHydrator<object>|null)  $classHydrator
     * @param Closure(class-string): (ClassExtractor<object>|null) $classExtractor
     */
    public function __construct(
        private readonly StackHydrator $hydrator,
        private readonly Closure $classHydrator,
        private readonly Closure $classExtractor,
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

    /**
     * @param class-string $class
     *
     * @return ClassHydrator<object>|null
     */
    public function classHydrator(string $class): ClassHydrator|null
    {
        return ($this->classHydrator)($class);
    }

    /**
     * @param class-string $class
     *
     * @return ClassExtractor<object>|null
     */
    public function classExtractor(string $class): ClassExtractor|null
    {
        return ($this->classExtractor)($class);
    }
}
