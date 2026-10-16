<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Handler\ExtractHandler;
use Patchlevel\Hydrator\Handler\HydrateHandler;
use Patchlevel\Hydrator\Handler\LazyHandler;
use Patchlevel\Hydrator\Handler\MiddlewareHandler;
use Patchlevel\Hydrator\Handler\NormalizerHandler;
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
use Patchlevel\Hydrator\Transformer\TransformerResolver;

use function array_key_exists;
use function assert;
use function is_array;

use const PHP_VERSION_ID;

/**
 * Decides once per class and direction what has to happen and keeps it as a handler: the transformer itself if
 * nothing else has to run, otherwise a handler which calls the class normalizer, the middlewares or creates a lazy
 * proxy. Every call is then a lookup of the handler and a call of it.
 */
final class StackHydrator implements Hydrator
{
    /** @var array<class-string, ClassMetadata> */
    private array $classMetadata = [];

    /** @var array<class-string, ClassTransformer> */
    private array $transformers = [];

    /** @var array<class-string, ClassTransformer|HydrateHandler> */
    private array $hydrateHandlers = [];

    /** @var array<class-string, ClassTransformer|ExtractHandler> */
    private array $extractHandlers = [];

    private readonly TransformerResolver $resolver;

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
        $this->resolver = new StackTransformerResolver(
            $this,
            fn (string $class): ClassTransformer|HydrateHandler => $this->hydrateHandlers[$class] ?? $this->hydrateHandler($class),
            fn (string $class): ClassTransformer|ExtractHandler => $this->extractHandlers[$class] ?? $this->extractHandler($class),
        );
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
            $handler = $this->hydrateHandlers[$class] ?? $this->hydrateHandler($class);
        } catch (ClassNotFound $e) {
            throw new ClassNotSupported($class, $e);
        }

        if ($handler instanceof ClassTransformer) {
            if (!is_array($data)) {
                throw new ArrayDataRequired($class);
            }

            $object = $handler->hydrate($data, $context);
        } else {
            $object = $handler->hydrate($data, $context);
        }

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
        $context[self::HYDRATOR] ??= $this;

        $handler = $this->extractHandlers[$object::class] ?? $this->extractHandler($object::class);

        return $handler->extract($object, $context);
    }

    /**
     * @param class-string $class
     *
     * @throws ClassNotFound
     */
    private function hydrateHandler(string $class): ClassTransformer|HydrateHandler
    {
        $metadata = $this->metadata($class);

        if ($metadata->normalizer !== null) {
            return $this->hydrateHandlers[$class] = new NormalizerHandler($class, $metadata->normalizer);
        }

        $transformer = $this->transformer($metadata);
        $middlewares = $this->middlewaresFor($metadata, Skip::Hydrate);

        $handler = $middlewares === []
            ? $transformer
            : new MiddlewareHandler($metadata, new Next($middlewares, $transformer));

        if (PHP_VERSION_ID >= 80400 && ($metadata->lazy ?? $this->defaultLazy)) {
            $handler = new LazyHandler($metadata->reflection, $handler);
        }

        return $this->hydrateHandlers[$class] = $handler;
    }

    /** @param class-string $class */
    private function extractHandler(string $class): ClassTransformer|ExtractHandler
    {
        $metadata = $this->metadata($class);

        if ($metadata->normalizer !== null) {
            return $this->extractHandlers[$class] = new NormalizerHandler($class, $metadata->normalizer);
        }

        $transformer = $this->transformer($metadata);
        $middlewares = $this->middlewaresFor($metadata, Skip::Extract);

        return $this->extractHandlers[$class] = $middlewares === []
            ? $transformer
            : new MiddlewareHandler($metadata, new Next($middlewares, $transformer));
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
     * @param ClassMetadata<T>            $metadata
     * @param Skip::Hydrate|Skip::Extract $direction
     *
     * @return list<Middleware>
     *
     * @template T of object
     */
    private function middlewaresFor(ClassMetadata $metadata, Skip $direction): array
    {
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

        return $middlewares;
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
