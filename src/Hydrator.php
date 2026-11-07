<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

interface Hydrator
{
    public const OBJECT_TO_POPULATE = 'object_to_populate';

    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @throws ClassNotSupported if the class is not supported or not found.
     * @throws DenormalizationFailure if any normalizers throw an exception.
     * @throws TypeMismatch if a TypeError occurs when setting a property value.
     * @throws HydratorException Any other thrown exceptions should implement HydratorException.
     *
     * @template T of object
     */
    public function hydrate(string $class, mixed $data, array $context = []): object;

    /**
     * @param array<string, mixed> $context
     *
     * @throws HydratorException
     */
    public function extract(object $object, array $context = []): mixed;
}
