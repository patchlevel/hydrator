<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\ClassNotFound;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Middleware\AllMiddlewaresSkipped;
use Patchlevel\Hydrator\Middleware\HydratorAwareMiddleware;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Middleware\TransformMiddleware;
use Patchlevel\Hydrator\Normalizer\HydratorAwareNormalizer;
use Patchlevel\Hydrator\Transformer\CallStack;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use Patchlevel\Hydrator\Transformer\ClassTransformerFactory;
use Patchlevel\Hydrator\Transformer\ReflectionTransformer;
use ReflectionClass;

use function array_key_exists;
use function assert;
use function is_array;

use const PHP_VERSION_ID;

final class StackHydrator implements Hydrator
{
    /** @var array<class-string, ClassMetadata> */
    private array $classMetadata = [];

    /** @var array<class-string, non-empty-list<Middleware>> */
    private array $hydrateMiddlewares = [];

    /** @var array<class-string, non-empty-list<Middleware>> */
    private array $extractMiddlewares = [];

    /** @var array<class-string, ClassTransformer> */
    private array $transformers = [];

    /** @var array<class-string, ClassTransformer> classes which only need the transformation, so the stack is skipped */
    private array $directHydrators = [];

    /** @var array<class-string, ClassTransformer> classes which only need the transformation, so the stack is skipped */
    private array $directExtractors = [];

    private readonly CallStack $callStack;

    private readonly bool $hasSkippableMiddlewares;

    /** The hydrator which is passed to the normalizers to hydrate and extract nested objects. */
    private Hydrator $rootHydrator;

    /**
     * @param list<Middleware>              $middlewares
     * @param list<ClassTransformerFactory> $transformerFactories asked in this order, reflection is the fallback
     */
    public function __construct(
        private readonly MetadataFactory $metadataFactory = new AttributeMetadataFactory(),
        private readonly array $middlewares = [new TransformMiddleware()],
        private readonly bool $defaultLazy = false,
        private readonly array $transformerFactories = [],
    ) {
        if ($middlewares === []) {
            throw new MissingMiddlewares();
        }

        $this->callStack = new CallStack();

        $hasSkippableMiddlewares = false;

        foreach ($middlewares as $middleware) {
            if ($middleware instanceof SkippableMiddleware) {
                $hasSkippableMiddlewares = true;
            }

            if (!$middleware instanceof HydratorAwareMiddleware) {
                continue;
            }

            $middleware->setHydrator($this);
        }

        $this->hasSkippableMiddlewares = $hasSkippableMiddlewares;
        $this->rootHydrator = $this;
    }

    /**
     * Sets the hydrator which wraps this one, so nested objects also go through its decorators.
     *
     * @internal
     */
    public function setRootHydrator(Hydrator $hydrator): void
    {
        $this->rootHydrator = $hydrator;

        foreach ($this->classMetadata as $metadata) {
            $this->injectHydrator($metadata);
        }
    }

    /**
     * The outermost hydrator, which wraps this one with all decorators. This one, if no decorator is registered.
     *
     * @internal
     */
    public function rootHydrator(): Hydrator
    {
        return $this->rootHydrator;
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
        $direct = $this->directHydrators[$class] ?? null;

        if ($direct !== null && is_array($data)) {
            $object = $direct->hydrate($data, $context);
            assert($object instanceof $class);

            return $object;
        }

        try {
            /** @var ClassMetadata<T> $metadata */
            $metadata = $this->classMetadata[$class] ?? $this->metadata($class);
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

        if (PHP_VERSION_ID < 80400 || !($metadata->lazy ?? $this->defaultLazy)) {
            $middlewares = $this->middlewaresFor($metadata, Skip::Hydrate);

            if (!isset($middlewares[1]) && $middlewares[0] instanceof TransformMiddleware) {
                // only the transformation runs for this class, from now on it is called without the stack
                $transformer = $this->directHydrators[$class] = $this->transformer($metadata);

                $object = $transformer->hydrate($data, $context);
                assert($object instanceof $class);

                return $object;
            }

            return $middlewares[0]->hydrate($metadata, $data, $context, new Stack($middlewares, 1));
        }

        return (new ReflectionClass($class))->newLazyProxy(
            function () use ($metadata, $data, $context): object {
                $middlewares = $this->middlewaresFor($metadata, Skip::Hydrate);

                if (!isset($middlewares[1]) && $middlewares[0] instanceof TransformMiddleware) {
                    // not cached as direct hydrator, every call has to create a lazy proxy again
                    $object = $this->transformer($metadata)->hydrate($data, $context);
                    assert($object instanceof $metadata->className);

                    return $object;
                }

                return $middlewares[0]->hydrate($metadata, $data, $context, new Stack($middlewares, 1));
            },
        );
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws HydratorException
     */
    public function extract(object $object, array $context = []): mixed
    {
        $direct = $this->directExtractors[$object::class] ?? null;

        if ($direct !== null) {
            return $direct->extract($object, $context);
        }

        $metadata = $this->classMetadata[$object::class] ?? $this->metadata($object::class);

        if ($metadata->normalizer) {
            return $metadata->normalizer->normalize($object, $context);
        }

        $middlewares = $this->middlewaresFor($metadata, Skip::Extract);

        if (!isset($middlewares[1]) && $middlewares[0] instanceof TransformMiddleware) {
            // only the transformation runs for this class, from now on it is called without the stack
            $transformer = $this->directExtractors[$object::class] = $this->transformer($metadata);

            return $transformer->extract($object, $context);
        }

        return $middlewares[0]->extract($metadata, $object, $context, new Stack($middlewares, 1));
    }

    /**
     * The transformer of the class, created once by the first factory which supports the class.
     *
     * @internal used by the {@see TransformMiddleware}
     *
     * @param ClassMetadata<T> $metadata
     *
     * @template T of object
     */
    public function transformer(ClassMetadata $metadata): ClassTransformer
    {
        $transformer = $this->transformers[$metadata->className] ?? null;

        if ($transformer !== null) {
            return $transformer;
        }

        foreach ($this->transformerFactories as $factory) {
            $transformer = $factory->create($metadata, $this);

            if ($transformer !== null) {
                return $this->transformers[$metadata->className] = $transformer;
            }
        }

        return $this->transformers[$metadata->className] = new ReflectionTransformer($metadata, $this->callStack);
    }

    /**
     * The objects which are currently extracted, transformers share it to detect circular references.
     *
     * @internal
     */
    public function callStack(): CallStack
    {
        return $this->callStack;
    }

    /**
     * @param ClassMetadata<T>            $metadata
     * @param Skip::Hydrate|Skip::Extract $direction
     *
     * @return non-empty-list<Middleware>
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

        if ($middlewares === []) {
            throw new AllMiddlewaresSkipped($metadata->className);
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

        $this->classMetadata[$class] = $metadata = $this->metadataFactory->metadata($class);

        $this->injectHydrator($metadata);

        return $metadata;
    }

    private function injectHydrator(ClassMetadata $metadata): void
    {
        foreach ($metadata->properties as $property) {
            if (!($property->normalizer instanceof HydratorAwareNormalizer)) {
                continue;
            }

            $property->normalizer->setHydrator($this->rootHydrator);
        }
    }
}
