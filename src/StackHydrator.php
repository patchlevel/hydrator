<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\ClassNotFound;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\MiddlewareExtractor;
use Patchlevel\Hydrator\Middleware\MiddlewareHydrator;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use Patchlevel\Hydrator\Transformer\ClassTransformerFactory;
use Patchlevel\Hydrator\Transformer\ReflectionTransformerFactory;
use Patchlevel\Hydrator\Transformer\TransformerResolver;

use function array_key_exists;
use function array_reverse;
use function assert;
use function is_array;

use const PHP_VERSION_ID;

/**
 * Builds once per class and direction what runs for it: a chain of the transformer of the class, wrapped in the
 * middlewares which do not skip the class and, while hydrating, a lazy proxy. If nothing else runs, the transformer
 * is the whole chain. A class with a class normalizer has no chain, the normalizer is called instead. Every call is
 * then a lookup of the chain and a call of it.
 */
final class StackHydrator implements Hydrator
{
    /** @var array<class-string, ClassMetadata> */
    private array $classMetadata = [];

    /** @var array<class-string, ClassTransformer<object>> */
    private array $transformers = [];

    /** @var array<class-string, ClassHydrator<object>> */
    private array $classHydrators = [];

    /** @var array<class-string, ClassExtractor<object>> */
    private array $classExtractors = [];

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
            fn (string $class): ClassHydrator|null => $this->classHydrators[$class] ?? $this->classHydrator($class),
            fn (string $class): ClassExtractor|null => $this->classExtractors[$class] ?? $this->classExtractor($class),
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
            $hydrator = $this->classHydrators[$class] ?? $this->classHydrator($class);
        } catch (ClassNotFound $e) {
            throw new ClassNotSupported($class, $e);
        }

        if ($hydrator === null) {
            return $this->denormalize($class, $data, $context);
        }

        if (!is_array($data)) {
            throw new ArrayDataRequired($class);
        }

        $object = $hydrator->hydrate($data, $context);
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

        $extractor = $this->classExtractors[$object::class] ?? $this->classExtractor($object::class);

        if ($extractor === null) {
            return $this->normalize($object, $context);
        }

        return $extractor->extract($object, $context);
    }

    /**
     * @param class-string $class
     *
     * @return ClassHydrator<object>|null null if the class has a class normalizer
     *
     * @throws ClassNotFound
     */
    private function classHydrator(string $class): ClassHydrator|null
    {
        $metadata = $this->metadata($class);

        if ($metadata->normalizer !== null) {
            return null;
        }

        $hydrator = $this->transformer($metadata);

        foreach (array_reverse($this->middlewaresFor($metadata, Skip::Hydrate)) as $middleware) {
            $hydrator = new MiddlewareHydrator($middleware, $metadata, $hydrator);
        }

        if (PHP_VERSION_ID >= 80400 && ($metadata->lazy ?? $this->defaultLazy)) {
            $hydrator = new LazyHydrator($metadata->reflection, $hydrator);
        }

        return $this->classHydrators[$class] = $hydrator;
    }

    /**
     * @param class-string $class
     *
     * @return ClassExtractor<object>|null null if the class has a class normalizer
     *
     * @throws ClassNotFound
     */
    private function classExtractor(string $class): ClassExtractor|null
    {
        $metadata = $this->metadata($class);

        if ($metadata->normalizer !== null) {
            return null;
        }

        $extractor = $this->transformer($metadata);

        foreach (array_reverse($this->middlewaresFor($metadata, Skip::Extract)) as $middleware) {
            $extractor = new MiddlewareExtractor($middleware, $metadata, $extractor);
        }

        return $this->classExtractors[$class] = $extractor;
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

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return ClassTransformer<object>
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
