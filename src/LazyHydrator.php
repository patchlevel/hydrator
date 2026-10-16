<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use ReflectionClass;

/**
 * The class is lazy: the object is a lazy proxy, the rest of the chain only runs when the object is used.
 *
 * @internal
 *
 * @template T of object
 * @implements ClassHydrator<T>
 */
final class LazyHydrator implements ClassHydrator
{
    /**
     * @param ReflectionClass<T> $reflection
     * @param ClassHydrator<T>   $next
     */
    public function __construct(
        private readonly ReflectionClass $reflection,
        private readonly ClassHydrator $next,
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
        $next = $this->next;

        return $this->reflection->newLazyProxy(
            static fn (): object => $next->hydrate($data, $context),
        );
    }
}
