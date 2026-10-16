<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Handler;

use Patchlevel\Hydrator\ArrayDataRequired;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use ReflectionClass;

use function is_array;

/**
 * The class is lazy: the object is a lazy proxy, the data is only hydrated when the object is used.
 *
 * @internal
 */
final class LazyHandler implements HydrateHandler
{
    /** @param ReflectionClass<object> $reflection */
    public function __construct(
        private readonly ReflectionClass $reflection,
        private readonly ClassTransformer|HydrateHandler $handler,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function hydrate(mixed $data, array $context): object
    {
        if (!is_array($data)) {
            throw new ArrayDataRequired($this->reflection->getName());
        }

        $handler = $this->handler;

        return $this->reflection->newLazyProxy(
            static fn (): object => $handler->hydrate($data, $context),
        );
    }
}
