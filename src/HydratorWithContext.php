<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

interface HydratorWithContext extends Hydrator
{
    public const OBJECT_TO_POPULATE = 'object_to_populate';

    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @throws ClassNotSupported if the class is not supported or not found.
     *
     * @template T of object
     */
    public function hydrate(string $class, array $data, array $context = []): object;

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extract(object $object, array $context = []): array;
}
