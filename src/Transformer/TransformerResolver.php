<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

use Patchlevel\Hydrator\Handler\ExtractHandler;
use Patchlevel\Hydrator\Handler\HydrateHandler;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\ClassNotFound;

/**
 * Gives a transformer insight into the hydrator which created it. A transformer can use it to map nested objects
 * itself, as long as the hydrator would not do anything else with them.
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
     * What the hydrator does to hydrate an object of the class. Calling it behaves exactly like calling the hydrator,
     * as long as the call came from {@see self::hydrator()}, without the detour through the normalizer and the
     * hydrator itself.
     *
     * @param class-string $class
     *
     * @return ClassTransformer|HydrateHandler the transformer, if nothing else has to run for the class
     *
     * @throws ClassNotFound
     */
    public function hydrateHandler(string $class): ClassTransformer|HydrateHandler;

    /**
     * What the hydrator does to extract an object of exactly this class, see {@see self::hydrateHandler()}.
     *
     * @param class-string $class
     *
     * @return ClassTransformer|ExtractHandler the transformer, if nothing else has to run for the class
     *
     * @throws ClassNotFound
     */
    public function extractHandler(string $class): ClassTransformer|ExtractHandler;

    /**
     * The transformer the hydrator calls directly for the class in this direction: the class has no class
     * normalizer, is not lazy and no middleware runs for it. Mapping such a class in place behaves exactly like
     * calling the hydrator, as long as the call came from {@see self::hydrator()}.
     *
     * @param class-string $class
     *
     * @return ClassTransformer|null null if the hydrator does more than calling the transformer
     *
     * @throws ClassNotFound
     */
    public function direct(string $class, Direction $direction): ClassTransformer|null;
}
