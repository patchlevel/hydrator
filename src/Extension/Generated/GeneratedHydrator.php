<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Closure;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Metadata\ClassNotFound;
use Patchlevel\Hydrator\StackHydrator;

use function assert;
use function is_array;

use const PHP_VERSION_ID;

/**
 * Decorates the {@see StackHydrator} and calls the generated code directly for the classes no other middleware has to
 * run for. Everything else is passed on to the stack.
 */
final class GeneratedHydrator implements Hydrator
{
    /** @var array<class-string, Closure|false> false if the class has no compiled hydrator */
    private array $hydrators = [];

    /** @var array<class-string, Closure|false> false if the class has no compiled extractor */
    private array $extractors = [];

    public function __construct(
        private readonly Hydrator $hydrator,
        private readonly StackHydrator $stack,
        private readonly GeneratedMiddleware $middleware,
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
        $hydrator = $this->hydrators[$class] ?? $this->compileHydrator($class);

        if ($hydrator !== false && is_array($data)) {
            $object = $hydrator($data, $context);
            assert($object instanceof $class);

            return $object;
        }

        return $this->hydrator->hydrate($class, $data, $context);
    }

    /** @param array<string, mixed> $context */
    public function extract(object $object, array $context = []): mixed
    {
        $extractor = $this->extractors[$object::class] ?? $this->compileExtractor($object::class);

        if ($extractor !== false) {
            return $extractor($object, $context);
        }

        return $this->hydrator->extract($object, $context);
    }

    /** @internal */
    public function stack(): StackHydrator
    {
        return $this->stack;
    }

    /** @internal */
    public function middleware(): GeneratedMiddleware
    {
        return $this->middleware;
    }

    /**
     * Decided once per class: the class must not use a class normalizer or a lazy proxy, and the generated
     * middleware must handle it exclusively. Everything else is left to the stack.
     *
     * @param class-string $class
     */
    private function compileHydrator(string $class): Closure|false
    {
        try {
            $metadata = $this->stack->metadata($class);
        } catch (ClassNotFound) {
            return $this->hydrators[$class] = false;
        }

        if ($metadata->normalizer !== null || (PHP_VERSION_ID >= 80400 && ($metadata->lazy ?? $this->stack->defaultLazy()))) {
            return $this->hydrators[$class] = false;
        }

        return $this->hydrators[$class] = $this->middleware->compiledHydrator($metadata) ?? false;
    }

    /** @param class-string $class */
    private function compileExtractor(string $class): Closure|false
    {
        $metadata = $this->stack->metadata($class);

        if ($metadata->normalizer !== null) {
            return $this->extractors[$class] = false;
        }

        return $this->extractors[$class] = $this->middleware->compiledExtractor($metadata) ?? false;
    }
}
