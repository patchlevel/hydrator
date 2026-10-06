<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

/**
 * A class together with the plans of its properties, its transformer is generated from it.
 *
 * @internal
 */
final class ClassPlan
{
    /**
     * @param list<PropertyPlan> $properties
     * @param bool               $scopedHydrate hydrate code must run in the scope of the class
     * @param bool               $scopedExtract extract code must run in the scope of the class
     */
    public function __construct(
        public readonly ClassMetadata $metadata,
        public readonly array $properties,
        public readonly bool $scopedHydrate,
        public readonly bool $scopedExtract,
    ) {
    }

    /** @return class-string */
    public function className(): string
    {
        return $this->metadata->className;
    }

    /** Classes without normalizers can never be part of a circular reference. */
    public function leaf(): bool
    {
        foreach ($this->properties as $property) {
            if ($property->kind !== ValueKind::Raw) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, NestedPlan> slot => nested class whose objects may be mapped in place */
    public function nested(): array
    {
        $nested = [];

        foreach ($this->properties as $property) {
            if ($property->nested === null) {
                continue;
            }

            $nested[$property->nested->slot] = $property->nested;
        }

        return $nested;
    }
}
