<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use ReflectionProperty;

use function count;

use const PHP_VERSION_ID;

/**
 * Decides how each property of a class is handled by its generated transformer.
 *
 * The planner accumulates the slots of a single class, create a new instance for every class.
 *
 * @internal
 */
final class ClassPlanner
{
    private int $normalizerSlots = 0;
    private int $flagSlots = 0;
    private int $defaultSlots = 0;

    /** @var array<class-string, int> nested class => slot of its transformer */
    private array $nestedSlots = [];

    /** @param ClassMetadata<object> $metadata */
    public function plan(ClassMetadata $metadata): ClassPlan
    {
        $properties = [];

        foreach ($metadata->properties as $property) {
            $properties[] = $this->property($metadata, $property);
        }

        [$scopedHydrate, $scopedExtract] = self::scoped($metadata, $properties);

        return new ClassPlan($metadata, $properties, $scopedHydrate, $scopedExtract);
    }

    /**
     * Constructor visibility does not matter, the generated code runs in the scope of the class. Anonymous classes
     * have no stable name the generated code could refer to.
     *
     * @param ClassMetadata<object> $metadata
     */
    public static function generatable(ClassMetadata $metadata): bool
    {
        $reflection = $metadata->reflection;

        return !$reflection->isInternal()
            && !$reflection->isAbstract()
            && !$reflection->isInterface()
            && !$reflection->isEnum()
            && !$reflection->isAnonymous();
    }

    /** @param ClassMetadata<object> $metadata */
    private function property(ClassMetadata $metadata, PropertyMetadata $property): PropertyPlan
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
            $nested = ClassFingerprint::nested($normalizer);

            if ($nested !== null) {
                $kind = $nested['array'] ? ValueKind::NestedArray : ValueKind::NestedObject;
                $flag = $this->flagSlots++;
                $this->nestedSlots[$nested['class']] ??= count($this->nestedSlots);

                if ($normalizer instanceof ArrayNormalizer) {
                    $inner = $this->normalizerSlots++;
                }
            }
        }

        if ($reflection->isPromoted()) {
            $parameter = $metadata->promotedConstructorDefaults()[$property->propertyName] ?? null;

            if ($parameter !== null) {
                // scalar defaults are inlined, everything else is resolved via reflection at runtime
                $default = Literal::export($parameter->getDefaultValue());

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
            nested: $nested === null ? null : new NestedPlan(
                $nested['class'],
                $this->nestedSlots[$nested['class']],
                $nested['final'],
            ),
            default: $default,
            defaultSlot: $defaultSlot,
            hydrateScope: $inherited && ($reflection->isPrivate() || $reflection->isReadOnly() || self::privateSet($reflection)) ? $declaringClass : null,
            extractScope: $inherited && $reflection->isPrivate() ? $declaringClass : null,
        );
    }

    /**
     * Code runs in the scope of the class (a bound closure) only if needed, otherwise in the methods of the transformer.
     *
     * @param ClassMetadata<object> $metadata
     * @param list<PropertyPlan>    $properties
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

    /** Readonly and asymmetric visibility restrict writes to the class scope. */
    private static function restrictedSet(ReflectionProperty $reflection): bool
    {
        return $reflection->isReadOnly() || self::privateSet($reflection) || (PHP_VERSION_ID >= 80400 && $reflection->isProtectedSet());
    }

    private static function privateSet(ReflectionProperty $reflection): bool
    {
        return PHP_VERSION_ID >= 80400 && $reflection->isPrivateSet();
    }
}
