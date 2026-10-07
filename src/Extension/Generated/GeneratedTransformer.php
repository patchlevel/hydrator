<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Closure;
use Patchlevel\Hydrator\Handler\ExtractHandler;
use Patchlevel\Hydrator\Handler\HydrateHandler;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\ArrayShapeNormalizer;
use Patchlevel\Hydrator\Normalizer\DateIntervalNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeZoneNormalizer;
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;
use Patchlevel\Hydrator\Normalizer\InlineNormalizer;
use Patchlevel\Hydrator\Normalizer\Normalizer;
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
 * The transformers are initialized on the first use: only then the hydrator knows what it does for the nested
 * classes. Nested objects are mapped with the handlers of their classes directly, and in place with the nested entry
 * points if the handler is a generated transformer.
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

    /**
     * @param ClassMetadata<object> $metadata
     * @param bool                  $circularReferenceCheck track extracted objects to detect circular references
     */
    public function __construct(
        public readonly ClassMetadata $metadata,
        public readonly TransformerResolver $resolver,
        public readonly CallStack $callStack,
        public readonly bool $circularReferenceCheck = true,
    ) {
    }

    /**
     * The code of classes with private properties runs in a closure bound to their scope. Other transformers call it
     * directly for nested objects, instead of {@see self::hydrateNested()} which only forwards to it.
     *
     * @var (Closure(array<string, mixed>, array<string, mixed>): object)|null
     */
    public Closure|null $nestedHydrator = null;

    /**
     * See {@see self::$nestedHydrator}.
     *
     * @var (Closure(object, array<string, mixed>): array<string, mixed>)|null
     */
    public Closure|null $nestedExtractor = null;

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
            $this->tracked = $this->circularReferenceCheck && $this->recursive();
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
     * The handler of a nested class, which maps its objects like the hydrator does, without the detour through the
     * normalizer and the hydrator. A generated transformer is mapped in place with its nested entry points.
     *
     * @param class-string $class
     */
    final protected function handler(Normalizer $normalizer, string $class, Direction $direction): ClassTransformer|HydrateHandler|ExtractHandler|null
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

            // initialized by the caller once all its normalizers are resolved, the nested class may lead back to it
            return $direction === Direction::Hydrate
                ? $this->resolver->hydrateHandler($class)
                : $this->resolver->extractHandler($class);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether the normalizer may extract objects with the hydrator, which can lead back to the same object. Only the
     * built-in normalizers which never call the hydrator are ruled out, every other normalizer could take it from the
     * context.
     */
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

        return !$normalizer instanceof DateTimeImmutableNormalizer
            && !$normalizer instanceof DateTimeNormalizer
            && !$normalizer instanceof DateTimeZoneNormalizer
            && !$normalizer instanceof DateIntervalNormalizer
            && !$normalizer instanceof EnumNormalizer
            && !$normalizer instanceof InlineNormalizer;
    }
}
