<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

/**
 * A nested class whose objects may be mapped in place by its generated transformer.
 *
 * @internal
 */
final class NestedPlan
{
    /**
     * @param class-string $class
     * @param int          $slot  slot of the transformer of the nested class, unique per nested class
     * @param bool         $final the exact class check of nested objects is cheaper for final classes
     */
    public function __construct(
        public readonly string $class,
        public readonly int $slot,
        public readonly bool $final,
    ) {
    }
}
