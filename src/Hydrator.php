<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

interface Hydrator
{
    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $data
     *
     * @return T
     *
     * @throws ClassNotSupported if the class is not supported or not found.
     *
     * @template T of object
     */
    public function hydrate(string $class, array $data): object;

    /** @return array<string, mixed> */
    public function extract(object $object): array;
}
