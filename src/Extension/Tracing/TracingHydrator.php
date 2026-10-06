<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Tracing;

use Patchlevel\Hydrator\Hydrator;

/**
 * Traces every call on the hydrator. Nested objects are hydrated and extracted inside the call of their parent,
 * so they are part of the parent's trace.
 */
final class TracingHydrator implements Hydrator
{
    public function __construct(
        private readonly Hydrator $hydrator,
        private readonly Tracer $tracer,
    ) {
    }

    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(string $class, mixed $data, array $context = []): object
    {
        return $this->tracer->trace(
            Operation::Hydrate,
            $class,
            fn (): object => $this->hydrator->hydrate($class, $data, $context),
        );
    }

    /** @param array<string, mixed> $context */
    public function extract(object $object, array $context = []): mixed
    {
        return $this->tracer->trace(
            Operation::Extract,
            $object::class,
            fn (): mixed => $this->hydrator->extract($object, $context),
        );
    }
}
