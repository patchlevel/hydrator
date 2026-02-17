<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\ClassNotFound;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;
use ReflectionProperty;
use UnitEnum;

use function array_key_exists;
use function array_values;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function ksort;
use function ltrim;
use function sprintf;
use function var_export;

use const PHP_VERSION_ID;

/**
 * Decides which classes end up in one generated middleware and how each of their properties is handled.
 *
 * The planner accumulates the state of a single dump, create a new instance for every middleware.
 *
 * @internal
 */
final class ClassPlanner
{
    /** @var array<class-string, int|null> class => index, null for classes which can not be part of the middleware */
    private array $index = [];

    /** @var array<int, ClassPlan> */
    private array $classes = [];

    private int $nextIndex = 0;
    private int $normalizerSlots = 0;
    private int $flagSlots = 0;
    private int $defaultSlots = 0;

    public function __construct(
        private readonly MetadataFactory $metadataFactory,
    ) {
    }

    /**
     * @param class-string $class
     *
     * @throws ClassNotGeneratable
     */
    public function add(string $class): void
    {
        $class = ltrim($class, '\\');

        try {
            $metadata = $this->metadataFactory->metadata($class);
        } catch (ClassNotFound) {
            throw new ClassNotGeneratable($class, 'class not found');
        }

        if ($metadata->normalizer !== null) {
            // handled by the hydrator itself, never reaches a middleware
            return;
        }

        if (!self::generatable($metadata)) {
            throw new ClassNotGeneratable($class, 'the class is internal, abstract or an interface');
        }

        $this->register($metadata);
    }

    /** @return list<ClassPlan> ordered by index */
    public function plans(): array
    {
        ksort($this->classes);

        return array_values($this->classes);
    }

    /** @return int index of the registered class */
    private function register(ClassMetadata $metadata): int
    {
        $class = $metadata->className;
        $index = $this->index[$class] ?? null;

        if ($index !== null) {
            return $index;
        }

        $index = $this->nextIndex++;
        // reserve the index before planning the properties: nested classes may reference this class again
        $this->index[$class] = $index;

        $properties = [];

        foreach ($metadata->properties as $property) {
            $properties[] = $this->plan($metadata, $property);
        }

        [$scopedHydrate, $scopedExtract] = self::scoped($metadata, $properties);

        $this->classes[$index] = new ClassPlan($index, $metadata, $properties, $scopedHydrate, $scopedExtract);

        return $index;
    }

    private function plan(ClassMetadata $metadata, PropertyMetadata $property): PropertyPlan
    {
        $reflection = $property->reflection;
        $declaringClass = $reflection->getDeclaringClass()->getName();
        $inherited = $declaringClass !== $metadata->className;

        $kind = ValueKind::Raw;
        $slot = null;
        $inner = null;
        $flag = null;
        $nested = null;
        $default = null;
        $defaultSlot = null;

        $normalizer = $property->normalizer;

        if ($normalizer !== null) {
            $kind = ValueKind::Normalizer;
            $slot = $this->normalizerSlots++;
            $nested = $this->nestedClass($normalizer);

            if ($nested !== null) {
                $kind = ValueKind::NestedObject;
            } elseif ($normalizer instanceof ArrayNormalizer) {
                $nested = $this->nestedClass($normalizer->innerNormalizer());

                if ($nested !== null) {
                    $kind = ValueKind::NestedArray;
                    $inner = $this->normalizerSlots++;
                }
            }

            if ($nested !== null) {
                $flag = $this->flagSlots++;
            }
        }

        if ($reflection->isPromoted()) {
            $parameter = $metadata->promotedConstructorDefaults()[$property->propertyName] ?? null;

            if ($parameter !== null) {
                // scalar defaults are inlined, everything else is resolved via reflection at runtime
                $default = self::literal($parameter->getDefaultValue());

                if ($default === null) {
                    $defaultSlot = $this->defaultSlots++;
                }
            }
        }

        return new PropertyPlan(
            name: $property->propertyName,
            field: $property->fieldName,
            kind: $kind,
            slot: $slot,
            inner: $inner,
            flag: $flag,
            nested: $nested,
            default: $default,
            defaultSlot: $defaultSlot,
            hydrateScope: $inherited && ($reflection->isPrivate() || $reflection->isReadOnly() || self::privateSet($reflection)) ? $declaringClass : null,
            extractScope: $inherited && $reflection->isPrivate() ? $declaringClass : null,
        );
    }

    /** @return int|null the index of the nested class if the normalizer is an object normalizer for an inlinable class */
    private function nestedClass(Normalizer $normalizer): int|null
    {
        if (!$normalizer instanceof ObjectNormalizer) {
            return null;
        }

        try {
            $class = $normalizer->className();
        } catch (InvalidType) {
            return null;
        }

        return $this->nestedIndex($class);
    }

    /**
     * Nested classes can only be inlined if they would end up in this middleware anyway.
     *
     * @param class-string $class
     */
    private function nestedIndex(string $class): int|null
    {
        if (array_key_exists($class, $this->index)) {
            return $this->index[$class];
        }

        try {
            $metadata = $this->metadataFactory->metadata($class);
        } catch (ClassNotFound) {
            return $this->index[$class] = null;
        }

        if ($metadata->normalizer !== null || $metadata->lazy === true || !self::generatable($metadata)) {
            return $this->index[$class] = null;
        }

        return $this->register($metadata);
    }

    /**
     * Code runs in the scope of the class (a bound closure) only if needed, otherwise plain methods are used.
     *
     * @param list<PropertyPlan> $properties
     *
     * @return array{bool, bool} [hydrate needs the class scope, extract needs the class scope]
     */
    private static function scoped(ClassMetadata $metadata, array $properties): array
    {
        $hydrate = false;
        $extract = false;

        foreach ($properties as $property) {
            if ($property->hydrateScope !== null || $property->extractScope !== null) {
                // handled by helper closures bound to the declaring class
                continue;
            }

            $reflection = $metadata->properties[$property->name]->reflection;

            if (!$reflection->isPublic()) {
                $hydrate = true;
                $extract = true;
            } elseif (self::restrictedSet($reflection)) {
                $hydrate = true;
            }
        }

        return [$hydrate, $extract];
    }

    /** Constructor visibility does not matter, the generated code runs in the scope of the class. */
    private static function generatable(ClassMetadata $metadata): bool
    {
        $reflection = $metadata->reflection;

        return !$reflection->isInternal() && !$reflection->isAbstract() && !$reflection->isInterface() && !$reflection->isEnum();
    }

    /** Readonly and asymmetric visibility restrict writes to the class scope. */
    private static function restrictedSet(ReflectionProperty $reflection): bool
    {
        return $reflection->isReadOnly() || self::privateSet($reflection) || (PHP_VERSION_ID >= 80400 && $reflection->isProtectedSet());
    }

    private static function privateSet(ReflectionProperty $reflection): bool
    {
        return PHP_VERSION_ID >= 80400 && $reflection->isPrivateSet();
    }

    /** @return string|null php expression for the value, null if it can not be expressed as a literal */
    private static function literal(mixed $value): string|null
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return var_export($value, true);
        }

        if ($value instanceof UnitEnum) {
            return sprintf('\\%s::%s', $value::class, $value->name);
        }

        if (is_array($value)) {
            $items = [];

            foreach ($value as $key => $item) {
                $literal = self::literal($item);

                if ($literal === null) {
                    return null;
                }

                $items[] = sprintf('%s => %s', var_export($key, true), $literal);
            }

            return '[' . implode(', ', $items) . ']';
        }

        return null;
    }
}
