<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use ReflectionClass;

final class LazyHydrator implements Hydrator
{
    public function __construct(
        private readonly Hydrator $realHydrator,
    ) {
    }

    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $data
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(string $class, array $data): object
    {
        return (new ReflectionClass($class))->newLazyProxy(
            function () use ($class, $data): object {
                return $this->realHydrator->hydrate($class, $data);
            },
        );
    }

    /** @return array<string, mixed> */
    public function extract(object $object): array
    {
        return $this->realHydrator->extract($object);
    }
}
