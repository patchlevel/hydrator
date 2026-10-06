<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\ClassNotFound;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
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

    /** @var list<Middleware> */
    private readonly array $middlewares;

    /** @var non-empty-list<Middleware> the middlewares followed by the transformation */
    private readonly array $pipeline;

    /** Ends every stack, it calls the transformer of the class. */
    private readonly TransformMiddleware $transform;

    /** @var array<class-string, non-empty-list<Middleware>> */
    private array $hydratePipelines = [];

    /** @var array<class-string, non-empty-list<Middleware>> */
    private array $extractPipelines = [];

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
     * The transformation always runs at the end, after all middlewares. A TransformMiddleware in the list is not
     * needed anymore, it ends the list like before: middlewares after it are never called.
     *
     * @param list<Middleware>             $middlewares
     * @param ClassTransformerFactory|null $transformerFactory provides other transformers than reflection
     */
    public function __construct(
        private readonly MetadataFactory $metadataFactory = new AttributeMetadataFactory(),
        array $middlewares = [],
        private readonly bool $defaultLazy = false,
        private readonly ClassTransformerFactory|null $transformerFactory = null,
    ) {
        $this->callStack = new CallStack();

        $this->transform = new TransformMiddleware();
        $this->transform->setHydrator($this);

        $list = [];
        $hasSkippableMiddlewares = false;

        foreach ($middlewares as $middleware) {
            if ($middleware instanceof TransformMiddleware) {
                break;
            }

            if ($middleware instanceof SkippableMiddleware) {
                $hasSkippableMiddlewares = true;
            }

            if ($middleware instanceof HydratorAwareMiddleware) {
                $middleware->setHydrator($this);
            }

            $list[] = $middleware;
        }

        $this->middlewares = $list;
        $this->pipeline = [...$list, $this->transform];
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

    /** @return list<Middleware> the middlewares, without the transformation at the end */
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
            $pipeline = $this->pipelineFor($metadata, Skip::Hydrate);

            if (!isset($pipeline[1])) {
                // no middleware runs for this class, from now on the transformer is called without the stack
                $transformer = $this->directHydrators[$class] = $this->transformer($metadata);

                $object = $transformer->hydrate($data, $context);
                assert($object instanceof $class);

                return $object;
            }

            return $pipeline[0]->hydrate($metadata, $data, $context, new Stack($pipeline, 1));
        }

        return (new ReflectionClass($class))->newLazyProxy(
            function () use ($metadata, $data, $context): object {
                $pipeline = $this->pipelineFor($metadata, Skip::Hydrate);

                if (!isset($pipeline[1])) {
                    // not cached as direct hydrator, every call has to create a lazy proxy again
                    $object = $this->transformer($metadata)->hydrate($data, $context);
                    assert($object instanceof $metadata->className);

                    return $object;
                }

                return $pipeline[0]->hydrate($metadata, $data, $context, new Stack($pipeline, 1));
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

        $pipeline = $this->pipelineFor($metadata, Skip::Extract);

        if (!isset($pipeline[1])) {
            // no middleware runs for this class, from now on the transformer is called without the stack
            $transformer = $this->directExtractors[$object::class] = $this->transformer($metadata);

            return $transformer->extract($object, $context);
        }

        return $pipeline[0]->extract($metadata, $object, $context, new Stack($pipeline, 1));
    }

    /**
     * The transformer of the class, created once by the transformer factory or reflection based as fallback.
     *
     * @internal used by the {@see TransformMiddleware} at the end of the stack
     *
     * @param ClassMetadata<T> $metadata
     *
     * @template T of object
     */
    public function transformer(ClassMetadata $metadata): ClassTransformer
    {
        return $this->transformers[$metadata->className]
            ??= $this->transformerFactory?->create($metadata, $this)
            ?? new ReflectionTransformer($metadata, $this->callStack);
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
     * The middlewares which run for the class in this direction, followed by the transformation.
     *
     * @param ClassMetadata<T>            $metadata
     * @param Skip::Hydrate|Skip::Extract $direction
     *
     * @return non-empty-list<Middleware>
     *
     * @template T of object
     */
    private function pipelineFor(ClassMetadata $metadata, Skip $direction): array
    {
        if (!$this->hasSkippableMiddlewares) {
            return $this->pipeline;
        }

        $cached = $direction === Skip::Hydrate
            ? $this->hydratePipelines[$metadata->className] ?? null
            : $this->extractPipelines[$metadata->className] ?? null;

        if ($cached !== null) {
            return $cached;
        }

        $pipeline = [];

        foreach ($this->middlewares as $middleware) {
            if ($middleware instanceof SkippableMiddleware) {
                $skip = $middleware->skip($metadata);

                if ($skip === $direction || $skip === Skip::Both) {
                    continue;
                }
            }

            $pipeline[] = $middleware;
        }

        $pipeline[] = $this->transform;

        if ($direction === Skip::Hydrate) {
            return $this->hydratePipelines[$metadata->className] = $pipeline;
        }

        return $this->extractPipelines[$metadata->className] = $pipeline;
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
