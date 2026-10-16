<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

use Patchlevel\Hydrator\ClassExtractor;
use Patchlevel\Hydrator\ClassHydrator;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\ClassNotFound;

/**
 * Gives a transformer insight into the hydrator which created it. A transformer can use it to map nested objects
 * with the chains the hydrator built for their classes, without the detour through the normalizer and the hydrator.
 *
 * Only use it once the transformer is used, not inside {@see ClassTransformerFactory::create()}: the hydrator has
 * not cached the transformer which is created at that moment.
 */
interface TransformerResolver
{
    /** The hydrator which created the transformer. */
    public function hydrator(): Hydrator;

    /**
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     *
     * @throws ClassNotFound
     *
     * @template T of object
     */
    public function metadata(string $class): ClassMetadata;

    /**
     * The chain the hydrator runs to hydrate an object of the class from an array. Calling it behaves exactly like
     * calling the hydrator, as long as the call came from {@see self::hydrator()}. If nothing else runs for the class,
     * it is the transformer of the class.
     *
     * @param class-string $class
     *
     * @return ClassHydrator<object>|null null if the class has a class normalizer, the hydrator calls the normalizer
     *                                    instead
     *
     * @throws ClassNotFound
     */
    public function classHydrator(string $class): ClassHydrator|null;

    /**
     * The chain the hydrator runs to extract an object of exactly this class, see {@see self::classHydrator()}.
     *
     * @param class-string $class
     *
     * @return ClassExtractor<object>|null null if the class has a class normalizer, the hydrator calls the normalizer
     *                                     instead
     *
     * @throws ClassNotFound
     */
    public function classExtractor(string $class): ClassExtractor|null;
}
