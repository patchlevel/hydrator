<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Mapper\CallStack;
use Patchlevel\Hydrator\Mapper\ChainPropertyFilter;
use Patchlevel\Hydrator\Mapper\LazyMapper;
use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Mapper\MapperFactory;
use Patchlevel\Hydrator\Mapper\PropertyFilter;
use Patchlevel\Hydrator\Mapper\ReflectionMapper;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\ClassNotFound;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\MiddlewareMapper;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;

use function array_key_exists;
use function array_reverse;
use function assert;
use function count;
use function is_array;

use const PHP_VERSION_ID;

/**
 * Maps every class with a mapper, built once per class: the mapper of a {@see MapperFactory} or the
 * {@see ReflectionMapper}, wrapped in the middlewares which run for the class and, if the class is lazy, the lazy
 * proxy. A class with a class normalizer has no mapper, the normalizer is called instead.
 */
final class StackHydrator implements Hydrator
{
    /** @var array<class-string, ClassMetadata> */
    private array $classMetadata = [];

    /** @var array<class-string, Mapper<object>> */
    private array $mappers = [];

    private readonly CallStack $callStack;

    private readonly PropertyFilter|null $propertyFilter;

    /**
     * The context of calls without a context, built once: adding the hydrator to an empty context would allocate a new
     * array on every call.
     *
     * @var array<string, mixed>
     */
    private readonly array $context;

    /**
     * @param list<Middleware>     $middlewares     wrap the mapper of every class, in this order
     * @param list<MapperFactory>  $mapperFactories asked in this order for the mapper of a class, the first mapper wins
     * @param list<PropertyFilter> $propertyFilters a property is only mapped if every filter keeps it
     */
    public function __construct(
        private readonly MetadataFactory $metadataFactory = new AttributeMetadataFactory(),
        private readonly array $middlewares = [],
        private readonly bool $defaultLazy = false,
        private readonly array $mapperFactories = [],
        array $propertyFilters = [],
    ) {
        $this->callStack = new CallStack();
        $this->context = [self::HYDRATOR => $this];
        $this->propertyFilter = match (count($propertyFilters)) {
            0 => null,
            1 => $propertyFilters[0],
            default => new ChainPropertyFilter($propertyFilters),
        };
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
        if ($context === []) {
            $context = $this->context;
        } else {
            $context[self::HYDRATOR] ??= $this;
        }

        $mapper = $this->mappers[$class] ?? $this->mapper($class);

        if ($mapper === null) {
            return $this->denormalize($class, $data, $context);
        }

        if (!is_array($data)) {
            throw new ArrayDataRequired($class);
        }

        $object = $mapper->hydrate($data, $context);
        assert($object instanceof $class);

        return $object;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws HydratorException
     */
    public function extract(object $object, array $context = []): mixed
    {
        if ($context === []) {
            $context = $this->context;
        } else {
            $context[self::HYDRATOR] ??= $this;
        }

        $mapper = $this->mappers[$object::class] ?? $this->mapper($object::class);

        if ($mapper === null) {
            return $this->normalize($object, $context);
        }

        return $mapper->extract($object, $context);
    }

    /**
     * The mapper the hydrator runs for the class. Calling it behaves exactly like calling the hydrator, as long as the
     * context contains this hydrator, see {@see Hydrator::HYDRATOR}.
     *
     * @param class-string<T> $class
     *
     * @return Mapper<T>|null null if the class has a class normalizer, the hydrator calls the normalizer instead
     *
     * @throws ClassNotSupported if the class is not found.
     *
     * @template T of object
     */
    public function mapper(string $class): Mapper|null
    {
        if (array_key_exists($class, $this->mappers)) {
            /** @var Mapper<T> $mapper */
            $mapper = $this->mappers[$class];

            return $mapper;
        }

        try {
            $metadata = $this->metadata($class);
        } catch (ClassNotFound $e) {
            throw new ClassNotSupported($class, $e);
        }

        if ($metadata->normalizer !== null) {
            return null;
        }

        $mapper = $this->createMapper($metadata);

        foreach (array_reverse($this->middlewares) as $middleware) {
            $skip = $middleware instanceof SkippableMiddleware ? $middleware->skip($metadata) : Skip::None;

            if ($skip === Skip::Both) {
                continue;
            }

            $mapper = new MiddlewareMapper($middleware, $metadata, $mapper, $skip);
        }

        if (PHP_VERSION_ID >= 80400 && ($metadata->lazy ?? $this->defaultLazy)) {
            $mapper = new LazyMapper($metadata->reflection, $mapper);
        }

        return $this->mappers[$class] = $mapper;
    }

    /**
     * The filter the mappers of this hydrator apply to the properties of every object, null if there is none. Mappers
     * of a {@see MapperFactory} have to apply it as well.
     */
    public function propertyFilter(): PropertyFilter|null
    {
        return $this->propertyFilter;
    }

    /**
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     *
     * @throws ClassNotFound
     *
     * @template T of object
     */
    public function metadata(string $class): ClassMetadata
    {
        if (array_key_exists($class, $this->classMetadata)) {
            return $this->classMetadata[$class];
        }

        return $this->classMetadata[$class] = $this->metadataFactory->metadata($class);
    }

    /**
     * @param ClassMetadata<T> $metadata
     *
     * @return Mapper<T>
     *
     * @template T of object
     */
    private function createMapper(ClassMetadata $metadata): Mapper
    {
        foreach ($this->mapperFactories as $factory) {
            $mapper = $factory->create($metadata, $this);

            if ($mapper !== null) {
                return $mapper;
            }
        }

        return new ReflectionMapper($metadata, $this, $this->callStack);
    }

    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @template T of object
     */
    private function denormalize(string $class, mixed $data, array $context): object
    {
        $normalizer = $this->metadata($class)->normalizer;
        assert($normalizer !== null);

        $object = $normalizer->denormalize($data, $context);

        if (!$object instanceof $class) {
            throw new ObjectRequired($class, $normalizer::class);
        }

        return $object;
    }

    /** @param array<string, mixed> $context */
    private function normalize(object $object, array $context): mixed
    {
        $normalizer = $this->metadata($object::class)->normalizer;
        assert($normalizer !== null);

        return $normalizer->normalize($object, $context);
    }
}
