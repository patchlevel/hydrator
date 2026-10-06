<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\ClassNotFound;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Next;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use Patchlevel\Hydrator\Transformer\ClassTransformerFactory;
use Patchlevel\Hydrator\Transformer\Direction;
use Patchlevel\Hydrator\Transformer\ReflectionTransformerFactory;
use Patchlevel\Hydrator\Transformer\TransformerResolver;
use ReflectionClass;

use function array_key_exists;
use function assert;
use function is_array;

use const PHP_VERSION_ID;

final class StackHydrator implements Hydrator
{
    /** @var array<class-string, ClassMetadata> */
    private array $classMetadata = [];

    /** @var array<class-string, list<Middleware>> */
    private array $hydrateMiddlewares = [];

    /** @var array<class-string, list<Middleware>> */
    private array $extractMiddlewares = [];

    /** @var array<class-string, ClassTransformer> */
    private array $transformers = [];

    /** @var array<class-string, ClassTransformer|false> false if the hydrator does more than calling the transformer */
    private array $directHydrate = [];

    /** @var array<class-string, ClassTransformer|false> false if the hydrator does more than calling the transformer */
    private array $directExtract = [];

    private readonly bool $hasSkippableMiddlewares;

    private readonly TransformerResolver $resolver;

    /**
     * The context of calls without context, built once: adding the hydrator to an empty context on every call would
     * allocate a new array each time.
     *
     * @var array<string, mixed>
     */
    private readonly array $context;

    /**
     * @param list<Middleware>        $middlewares        run before the data is transformed, in this order
     * @param ClassTransformerFactory $transformerFactory provides the transformer which maps the data of a class
     */
    public function __construct(
        private readonly MetadataFactory $metadataFactory = new AttributeMetadataFactory(),
        private readonly array $middlewares = [],
        private readonly bool $defaultLazy = false,
        private readonly ClassTransformerFactory $transformerFactory = new ReflectionTransformerFactory(),
    ) {
        $hasSkippableMiddlewares = false;

        foreach ($middlewares as $middleware) {
            if ($middleware instanceof SkippableMiddleware) {
                $hasSkippableMiddlewares = true;

                break;
            }
        }

        $this->hasSkippableMiddlewares = $hasSkippableMiddlewares;
        $this->resolver = new StackTransformerResolver($this, $this->direct(...));
        $this->context = [self::HYDRATOR => $this];
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

        $transformer = $this->directHydrate[$class] ?? false;

        // nothing else to do for this class, the transformer is called right away
        if ($transformer !== false && is_array($data)) {
            $object = $transformer->hydrate($data, $context);
            assert($object instanceof $class);

            return $object;
        }

        return $this->hydrateClass($class, $data, $context);
    }

    /**
     * The path for classes seen the first time and for classes which need more than the transformer, kept out of
     * {@see self::hydrate()} to keep the direct path small.
     *
     * @param class-string<T>      $class
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @template T of object
     */
    private function hydrateClass(string $class, mixed $data, array $context): object
    {
        try {
            $transformer = $this->directHydrate[$class] ?? $this->direct($class, Direction::Hydrate);
        } catch (ClassNotFound $e) {
            throw new ClassNotSupported($class, $e);
        }

        if ($transformer !== false && is_array($data)) {
            $object = $transformer->hydrate($data, $context);
            assert($object instanceof $class);

            return $object;
        }

        /** @var ClassMetadata<T> $metadata the metadata is already loaded */
        $metadata = $this->classMetadata[$class];

        if ($metadata->normalizer) {
            $return = $metadata->normalizer->denormalize($data, $context);

            if (!$return instanceof $class) {
                throw new ObjectRequired($class, $metadata->normalizer::class);
            }

            return $return;
        }

        if (!is_array($data)) {
            throw new ArrayDataRequired($class);
        }

        if (!$this->lazy($metadata)) {
            return $this->hydrateWithMiddlewares($metadata, $data, $context);
        }

        return (new ReflectionClass($class))->newLazyProxy(
            fn (): object => $this->hydrateWithMiddlewares($metadata, $data, $context),
        );
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

        $transformer = $this->directExtract[$object::class] ?? false;

        // nothing else to do for this class, the transformer is called right away
        if ($transformer !== false) {
            return $transformer->extract($object, $context);
        }

        return $this->extractClass($object, $context);
    }

    /**
     * The path for classes seen the first time and for classes which need more than the transformer, kept out of
     * {@see self::extract()} to keep the direct path small.
     *
     * @param array<string, mixed> $context
     */
    private function extractClass(object $object, array $context): mixed
    {
        $transformer = $this->directExtract[$object::class] ?? $this->direct($object::class, Direction::Extract);

        if ($transformer !== false) {
            return $transformer->extract($object, $context);
        }

        $metadata = $this->classMetadata[$object::class];

        if ($metadata->normalizer) {
            return $metadata->normalizer->normalize($object, $context);
        }

        return (new Next($this->middlewaresFor($metadata, Direction::Extract), $this->transformer($metadata)))
            ->extract($metadata, $object, $context);
    }

    /**
     * Decided once per class and direction: the transformer can be called directly if the class has no class
     * normalizer, is not lazy and no middleware runs for it.
     *
     * @param class-string $class
     *
     * @throws ClassNotFound
     */
    private function direct(string $class, Direction $direction): ClassTransformer|false
    {
        $metadata = $this->metadata($class);
        $direct = false;

        if (
            $metadata->normalizer === null
            && ($direction === Direction::Extract || !$this->lazy($metadata))
            && $this->middlewaresFor($metadata, $direction) === []
        ) {
            $direct = $this->transformer($metadata);
        }

        if ($direction === Direction::Hydrate) {
            return $this->directHydrate[$class] = $direct;
        }

        return $this->directExtract[$class] = $direct;
    }

    /** @param ClassMetadata<object> $metadata */
    private function lazy(ClassMetadata $metadata): bool
    {
        return PHP_VERSION_ID >= 80400 && ($metadata->lazy ?? $this->defaultLazy);
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @template T of object
     */
    private function hydrateWithMiddlewares(ClassMetadata $metadata, array $data, array $context): object
    {
        $middlewares = $this->middlewaresFor($metadata, Direction::Hydrate);
        $transformer = $this->transformer($metadata);

        if ($middlewares === []) {
            // no middleware runs for this class, the transformer is called without building the stack
            $object = $transformer->hydrate($data, $context);
            assert($object instanceof $metadata->className);

            return $object;
        }

        return (new Next($middlewares, $transformer))->hydrate($metadata, $data, $context);
    }

    /**
     * @param ClassMetadata<T> $metadata
     *
     * @template T of object
     */
    private function transformer(ClassMetadata $metadata): ClassTransformer
    {
        return $this->transformers[$metadata->className]
            ??= $this->transformerFactory->create($metadata, $this->resolver)
            ?? throw new ClassNotSupported($metadata->className);
    }

    /**
     * @param ClassMetadata<T> $metadata
     *
     * @return list<Middleware>
     *
     * @template T of object
     */
    private function middlewaresFor(ClassMetadata $metadata, Direction $direction): array
    {
        if (!$this->hasSkippableMiddlewares) {
            return $this->middlewares;
        }

        $cached = $direction === Direction::Hydrate
            ? $this->hydrateMiddlewares[$metadata->className] ?? null
            : $this->extractMiddlewares[$metadata->className] ?? null;

        if ($cached !== null) {
            return $cached;
        }

        $middlewares = [];
        $skipped = $direction === Direction::Hydrate ? Skip::Hydrate : Skip::Extract;

        foreach ($this->middlewares as $middleware) {
            if ($middleware instanceof SkippableMiddleware) {
                $skip = $middleware->skip($metadata);

                if ($skip === $skipped || $skip === Skip::Both) {
                    continue;
                }
            }

            $middlewares[] = $middleware;
        }

        if ($direction === Direction::Hydrate) {
            return $this->hydrateMiddlewares[$metadata->className] = $middlewares;
        }

        return $this->extractMiddlewares[$metadata->className] = $middlewares;
    }

    /**
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
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
}
