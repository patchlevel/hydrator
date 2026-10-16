<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

/**
 * Extracts the objects of one class to an array, the counterpart of the {@see ClassHydrator}: the
 * {@see Transformer\ClassTransformer} of the class at the end, wrapped in the middlewares which run for the class.
 *
 * @template-contravariant T of object
 */
interface ClassExtractor
{
    /**
     * @param T                    $object
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     *
     * @throws HydratorException
     */
    public function extract(object $object, array $context): array;
}
