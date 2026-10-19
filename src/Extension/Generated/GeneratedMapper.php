<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Mapper\CallStack;
use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\ArrayShapeNormalizer;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\ObjectMapNormalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;
use Patchlevel\Hydrator\StackHydrator;
use ReflectionClass;
use ReflectionParameter;
use Throwable;

/**
 * Base of the generated mappers.
 *
 * The mappers are initialized on the first use: only then the hydrator has built the mappers of the nested classes.
 * Nested objects are mapped with the mapper of their class right away, for calls of the hydrator which created this
 * mapper. If the hydrator has a property filter, the mapper is wrapped in a {@see FilteredMapper}.
 *
 * The members are public, because the generated code also runs in closures bound to the scope of the mapped class to
 * access its private properties.
 *
 * @internal
 *
 * @implements Mapper<object>
 */
abstract class GeneratedMapper implements Mapper, NestedMapper
{
    public bool $ready = false;
    public bool $initializing = false;
    public bool|null $recursive = null;

    /** Whether extracted objects are tracked to detect circular references. */
    public bool $tracked = false;

    /** @var ReflectionClass<object> */
    public ReflectionClass $reflection;

    /** @param ClassMetadata<object> $metadata */
    public function __construct(
        public readonly ClassMetadata $metadata,
        public readonly StackHydrator $hydrator,
        public readonly CallStack $callStack,
    ) {
    }

    final public function init(): void
    {
        if ($this->ready || $this->initializing) {
            return;
        }

        // ready is only set at the end, so a failed initialization runs again on the next call instead of leaving
        // a half initialized mapper behind. initializing stops the recursion between nested classes meanwhile.
        $this->initializing = true;

        try {
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

    /**
     * Only reads the properties the property filter keeps, see {@see FilteredMapper}.
     *
     * @param array<string, mixed>            $context
     * @param array<string, PropertyMetadata> $fields  the properties to extract by field name
     * @param bool                            $inline  nested objects may be mapped with the mapper of their class right away
     *
     * @return array<string, mixed>
     */
    abstract public function extractFiltered(object $object, array $context, array $fields, bool $inline): array;

    /** Resolves the normalizers and the mappers of the nested classes. */
    abstract protected function initialize(): void;

    abstract protected function mayReachItself(): bool;

    final protected function normalizer(string $property): Normalizer
    {
        return $this->metadata->properties[$property]->normalizer
            ?? throw new OutdatedGeneratedMapper($this->metadata->className);
    }

    final protected function promotedDefault(string $property): ReflectionParameter
    {
        return $this->metadata->promotedConstructorDefaults()[$property]
            ?? throw new OutdatedGeneratedMapper($this->metadata->className);
    }

    /**
     * The mapper the hydrator runs for the nested class, if the objects of the property can be mapped with it right
     * away: the property still has the object normalizer of the class the code was generated for and the nested
     * class has no class normalizer. A generated mapper is called directly, also inside a {@see FilteredMapper}, any
     * other chain, for example with middlewares, through a {@see ChainNestedMapper}.
     *
     * @param class-string $class
     */
    final protected function nested(Normalizer $normalizer, string $class): NestedMapper|null
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

            $mapper = $this->hydrator->mapper($class);
        } catch (Throwable) {
            return null;
        }

        if ($mapper === null || $mapper instanceof self || $mapper instanceof FilteredMapper) {
            return $mapper;
        }

        return new ChainNestedMapper($mapper);
    }

    /** Initializes a nested generated mapper, the nested class may lead back to this one. */
    final protected static function initNested(NestedMapper|null $mapper): void
    {
        if ($mapper instanceof FilteredMapper) {
            $mapper = $mapper->mapper;
        }

        if (!$mapper instanceof self) {
            return;
        }

        $mapper->init();
    }

    /** Whether objects extracted with the mapper can lead back to the same object. */
    final protected static function reaches(NestedMapper $mapper): bool
    {
        if ($mapper instanceof FilteredMapper) {
            $mapper = $mapper->mapper;
        }

        // the middlewares and the lazy proxy of a chain are not known, they might
        return !$mapper instanceof self || $mapper->recursive();
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
