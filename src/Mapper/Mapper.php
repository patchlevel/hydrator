<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Mapper;

/**
 * Maps the data of one class to an object and back.
 *
 * The hydrator builds one mapper per class, once: the mapper of the class, wrapped in the middlewares which run for the
 * class and, if the class is lazy, the lazy proxy. A middleware receives the rest of this chain as its next mapper.
 *
 * @template T of object
 */
interface Mapper
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context may contain an object to populate, see {@see \Patchlevel\Hydrator\Hydrator::OBJECT_TO_POPULATE}
     *
     * @return T
     */
    public function hydrate(array $data, array $context): object;

    /**
     * @param T                    $object
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extract(object $object, array $context): array;
}
