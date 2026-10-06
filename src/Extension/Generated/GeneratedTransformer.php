<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\ArrayShapeNormalizer;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\ObjectMapNormalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;
use Patchlevel\Hydrator\Transformer\CallStack;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use Patchlevel\Hydrator\Transformer\Direction;
use Patchlevel\Hydrator\Transformer\TransformerResolver;
use ReflectionClass;
use ReflectionParameter;
use Throwable;

/**
 * Base of the generated transformers.
 *
 * The transformers are initialized on the first use: only then the hydrator knows whether it passes the nested
 * classes to generated transformers directly. Only then nested objects are mapped in place, with the nested entry
 * points of the transformer of the nested class.
 *
 * The members are public, because the generated code also runs in closures bound to the scope of the transformed
 * class to access its private properties.
 *
 * @internal
 */
abstract class GeneratedTransformer implements ClassTransformer
{
    public bool $ready = false;
    public bool $initializing = false;
    public bool|null $recursive = null;

    /** Whether extracted objects are tracked to detect circular references. */
    public bool $tracked = false;

    /** The hydrator which created the transformer, nested objects are only mapped in place for calls from it. */
    public Hydrator $owner;

    /** @var ReflectionClass<object> */
    public ReflectionClass $reflection;

    /** @param ClassMetadata<object> $metadata */
    public function __construct(
        public readonly ClassMetadata $metadata,
        public readonly TransformerResolver $resolver,
        public readonly CallStack $callStack,
    ) {
    }

    /**
     * Hydrates a nested object in place. Called by an initialized transformer for calls from the owner, without an
     * object to populate.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    abstract public function hydrateNested(array $data, array $context): object;

    /**
     * Extracts a nested object in place, see {@see self::hydrateNested()}.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    abstract public function extractNested(object $object, array $context): array;

    final public function init(): void
    {
        if ($this->ready || $this->initializing) {
            return;
        }

        // ready is only set at the end, so a failed initialization runs again on the next call instead of leaving
        // a half initialized transformer behind. initializing stops the recursion between nested classes meanwhile.
        $this->initializing = true;

        try {
            $this->owner = $this->resolver->hydrator();
            $this->reflection = $this->metadata->reflection;
            $this->initialize();
            $this->tracked = $this->recursive();
            $this->ready = true;
        } finally {
            $this->initializing = false;
        }
    }

    /** Whether an extracted object can lead back to an object of this class, see {@see self::$tracked}. */
    final public function recursive(): bool
    {
        if ($this->recursive !== null) {
            return $this->recursive;
        }

        // cycles are treated as recursive
        $this->recursive = true;

        return $this->recursive = $this->mayReachItself();
    }

    /** Resolves the normalizers and the nested transformers. */
    abstract protected function initialize(): void;

    abstract protected function mayReachItself(): bool;

    final protected function normalizer(string $property): Normalizer
    {
        return $this->metadata->properties[$property]->normalizer
            ?? throw new OutdatedGeneratedTransformer($this->metadata->className);
    }

    final protected function promotedDefault(string $property): ReflectionParameter
    {
        return $this->metadata->promotedConstructorDefaults()[$property]
            ?? throw new OutdatedGeneratedTransformer($this->metadata->className);
    }

    /**
     * The generated transformer of a nested class, if nested objects can be mapped in place: the hydrator must call
     * the generated transformer of the nested class directly, without a middleware, a class normalizer or a lazy proxy.
     *
     * @param class-string $class
     */
    final protected function nested(Normalizer $normalizer, string $class, Direction $direction): GeneratedTransformer|null
    {
        if ($normalizer instanceof ArrayNormalizer) {
            $normalizer = $normalizer->innerNormalizer();
        }

        if (!$normalizer instanceof ObjectNormalizer) {
            return null;
        }

        try {
            if ($normalizer->className() !== $class) {
                return null;
            }

            $transformer = $this->resolver->direct($class, $direction);
        } catch (Throwable) {
            return null;
        }

        // initialized by the caller once all its normalizers are resolved, the nested class may lead back to it
        return $transformer instanceof self ? $transformer : null;
    }

    /** Whether the normalizer extracts objects with the hydrator, which can lead back to the same object. */
    final protected static function mayRecurse(Normalizer $normalizer): bool
    {
        if ($normalizer instanceof ArrayNormalizer) {
            return self::mayRecurse($normalizer->innerNormalizer());
        }

        if ($normalizer instanceof ArrayShapeNormalizer) {
            foreach ($normalizer->innerNormalizers() as $inner) {
                if (self::mayRecurse($inner)) {
                    return true;
                }
            }

            return false;
        }

        return $normalizer instanceof ObjectNormalizer || $normalizer instanceof ObjectMapNormalizer;
    }
}
