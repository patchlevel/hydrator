<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

interface Hydrator
{
    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @throws ClassNotSupported if the class is not supported or not found.
     *
     * @template T of object
     */
    public function hydrate(string $class, mixed $data, array $context = []): object;

    /** @param array<string, mixed> $context */
    public function extract(object $object, array $context = []): mixed;
}
