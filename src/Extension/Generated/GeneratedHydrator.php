<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Closure;
use Patchlevel\Hydrator\Metadata\ClassNotFound;
use Patchlevel\Hydrator\StackHydrator;

use function array_filter;
use function array_values;
use function assert;
use function is_array;

use const PHP_VERSION_ID;

/**
 * A {@see StackHydrator} which calls the generated code directly for the classes no other middleware has to run for.
 * Everything else takes the regular path through the middleware stack.
 */
final class GeneratedHydrator extends StackHydrator
{
    /** @var array<class-string, Closure|false> false if the class has no compiled hydrator */
    private array $hydrators = [];

    /** @var array<class-string, Closure|false> false if the class has no compiled extractor */
    private array $extractors = [];

    /** @var list<GeneratedMiddleware>|null */
    private array|null $generated = null;

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

        return parent::hydrate($class, $data, $context);
    }

    /** @param array<string, mixed> $context */
    public function extract(object $object, array $context = []): mixed
    {
        $extractor = $this->extractors[$object::class] ?? $this->compileExtractor($object::class);

        if ($extractor !== false) {
            return $extractor($object, $context);
        }

        return parent::extract($object, $context);
    }

    /**
     * Decided once per class: the class must not use a class normalizer or a lazy proxy, and a generated
     * middleware must handle it exclusively. Everything else is left to the stack.
     *
     * @param class-string $class
     */
    private function compileHydrator(string $class): Closure|false
    {
        try {
            $metadata = $this->metadata($class);
        } catch (ClassNotFound) {
            return $this->hydrators[$class] = false;
        }

        if ($metadata->normalizer !== null || (PHP_VERSION_ID >= 80400 && ($metadata->lazy ?? $this->defaultLazy()))) {
            return $this->hydrators[$class] = false;
        }

        foreach ($this->generated() as $middleware) {
            $hydrator = $middleware->compiledHydrator($metadata);

            if ($hydrator !== null) {
                return $this->hydrators[$class] = $hydrator;
            }
        }

        return $this->hydrators[$class] = false;
    }

    /** @param class-string $class */
    private function compileExtractor(string $class): Closure|false
    {
        $metadata = $this->metadata($class);

        if ($metadata->normalizer !== null) {
            return $this->extractors[$class] = false;
        }

        foreach ($this->generated() as $middleware) {
            $extractor = $middleware->compiledExtractor($metadata);

            if ($extractor !== null) {
                return $this->extractors[$class] = $extractor;
            }
        }

        return $this->extractors[$class] = false;
    }

    /** @return list<GeneratedMiddleware> */
    private function generated(): array
    {
        return $this->generated ??= array_values(array_filter(
            $this->middlewares(),
            static fn ($middleware): bool => $middleware instanceof GeneratedMiddleware,
        ));
    }
}
