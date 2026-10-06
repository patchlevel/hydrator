<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

/**
 * Maps the data of one class to an object and back. It is the last step of the middleware stack, called by the
 * {@see \Patchlevel\Hydrator\Middleware\TransformMiddleware}, or directly by the hydrator if no other middleware has
 * to run for the class.
 */
interface ClassTransformer
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context may contain an object to populate, see {@see \Patchlevel\Hydrator\Hydrator::OBJECT_TO_POPULATE}
     */
    public function hydrate(array $data, array $context): object;

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extract(object $object, array $context): array;
}
