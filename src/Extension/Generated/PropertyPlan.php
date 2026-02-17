<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

/**
 * How a single property is hydrated and extracted by the generated middleware.
 *
 * Slots are the numeric suffixes of the properties of the generated class ($n0, $ih1, $d2, ...). They are unique
 * within one middleware.
 *
 * @internal
 */
final class PropertyPlan
{
    /**
     * @param int|null          $slot         slot of the normalizer, null for raw values
     * @param int|null          $inner        slot of the inner normalizer of a nested array
     * @param int|null          $flag         slot of the inline flags of a nested value
     * @param int|null          $nested       index of the nested class
     * @param string|null       $default      php expression of an inlined promoted default
     * @param int|null          $defaultSlot  slot of a promoted default which is resolved at runtime
     * @param class-string|null $hydrateScope class whose scope is needed to write the property
     * @param class-string|null $extractScope class whose scope is needed to read the property
     */
    public function __construct(
        public readonly string $name,
        public readonly string $field,
        public readonly ValueKind $kind,
        public readonly int|null $slot = null,
        public readonly int|null $inner = null,
        public readonly int|null $flag = null,
        public readonly int|null $nested = null,
        public readonly string|null $default = null,
        public readonly int|null $defaultSlot = null,
        public readonly string|null $hydrateScope = null,
        public readonly string|null $extractScope = null,
    ) {
    }

    public function hasDefault(): bool
    {
        return $this->default !== null || $this->defaultSlot !== null;
    }
}
