<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

/**
 * Hydrates the objects of one class from an array. The hydrator builds one chain of them per class, decided once: the
 * {@see Transformer\ClassTransformer} of the class at the end, wrapped in the middlewares and the lazy proxy which
 * run for the class. Each one only knows the next one in the chain.
 *
 * A class with a class normalizer has no chain, the hydrator calls the normalizer instead.
 *
 * @template-covariant T of object
 */
interface ClassHydrator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @throws HydratorException
     */
    public function hydrate(array $data, array $context): object;
}
