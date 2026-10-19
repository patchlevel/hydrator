<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Mapper;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;

/**
 * Decides which properties of a class are mapped for a call, for example by the groups in the context. Filters are
 * registered with {@see \Patchlevel\Hydrator\StackHydratorBuilder::addPropertyFilter()}, the mappers of the hydrator
 * apply them, see {@see \Patchlevel\Hydrator\StackHydrator::propertyFilter()}.
 *
 * Properties outside the filter are not read while extracting, their fields are left out. While hydrating their fields
 * are treated as missing: new objects fall back to the default value of promoted properties, existing objects keep the
 * values of these properties.
 */
interface PropertyFilter
{
    /**
     * Called for every mapped object, cache the result.
     *
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $context
     *
     * @return array<string, PropertyMetadata>|null the properties to map by field name, in the order of the metadata, or null to map every property
     *
     * @template T of object
     */
    public function filter(ClassMetadata $metadata, array $context): array|null;
}
