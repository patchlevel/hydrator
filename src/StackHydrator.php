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
use Patchlevel\Hydrator\Transformer\ReflectionTransformerFactory;
use ReflectionClass;

use function array_key_exists;
use function assert;
use function is_array;

use const PHP_VERSION_ID;

/**
 * Also a metadata factory: it returns the metadata it works with, including all guessers, enrichers and the cache.
 */
final class StackHydrator implements Hydrator, MetadataFactory
{
    /** @var array<class-string, ClassMetadata> */
    private array $classMetadata = [];

    /** @var array<class-string, list<Middleware>> */
    private array $hydrateMiddlewares = [];

    /** @var array<class-string, list<Middleware>> */
    private array $extractMiddlewares = [];

    /** @var array<class-string, ClassTransformer> */
    private array $transformers = [];

    private readonly bool $hasSkippableMiddlewares;

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
    }

    /** @return list<Middleware> */
    public function middlewares(): array
    {
        return $this->middlewares;
    }

    public function defaultLazy(): bool
    {
        return $this->defaultLazy;
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
        $context[self::HYDRATOR] ??= $this;

        try {
            $metadata = $this->metadata($class);
        } catch (ClassNotFound $e) {
            throw new ClassNotSupported($class, $e);
        }

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

        $lazy = PHP_VERSION_ID >= 80400 && ($metadata->lazy ?? $this->defaultLazy);

        if (!$lazy) {
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
        $context[self::HYDRATOR] ??= $this;

        $metadata = $this->metadata($object::class);

        if ($metadata->normalizer) {
            return $metadata->normalizer->normalize($object, $context);
        }

        $middlewares = $this->middlewaresFor($metadata, Skip::Extract);
        $transformer = $this->transformer($metadata);

        // without middlewares the transformer is called without building the stack
        return $middlewares === []
            ? $transformer->extract($object, $context)
            : (new Next($middlewares, $transformer))->extract($metadata, $object, $context);
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
        $middlewares = $this->middlewaresFor($metadata, Skip::Hydrate);
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
            ??= $this->transformerFactory->create($metadata, $this)
            ?? throw new ClassNotSupported($metadata->className);
    }

    /**
     * @param ClassMetadata<T>            $metadata
     * @param Skip::Hydrate|Skip::Extract $direction
     *
     * @return list<Middleware>
     *
     * @template T of object
     */
    private function middlewaresFor(ClassMetadata $metadata, Skip $direction): array
    {
        if (!$this->hasSkippableMiddlewares) {
            return $this->middlewares;
        }

        $cached = $direction === Skip::Hydrate
            ? $this->hydrateMiddlewares[$metadata->className] ?? null
            : $this->extractMiddlewares[$metadata->className] ?? null;

        if ($cached !== null) {
            return $cached;
        }

        $middlewares = [];

        foreach ($this->middlewares as $middleware) {
            if ($middleware instanceof SkippableMiddleware) {
                $skip = $middleware->skip($metadata);

                if ($skip === $direction || $skip === Skip::Both) {
                    continue;
                }
            }

            $middlewares[] = $middleware;
        }

        if ($direction === Skip::Hydrate) {
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
