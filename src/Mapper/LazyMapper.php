<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Mapper;

use ReflectionClass;

/**
 * Hydrates a lazy class: the object is a lazy proxy, the wrapped mapper only runs once the object is used.
 *
 * @internal
 *
 * @template T of object
 * @implements Mapper<T>
 */
final class LazyMapper implements Mapper
{
    /**
     * @param ReflectionClass<T> $reflection
     * @param Mapper<T>          $mapper
     */
    public function __construct(
        private readonly ReflectionClass $reflection,
        private readonly Mapper $mapper,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     */
    public function hydrate(array $data, array $context): object
    {
        $mapper = $this->mapper;

        return $this->reflection->newLazyProxy(
            static fn (): object => $mapper->hydrate($data, $context),
        );
    }

    /**
     * @param T                    $object
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extract(object $object, array $context): array
    {
        return $this->mapper->extract($object, $context);
    }
}
