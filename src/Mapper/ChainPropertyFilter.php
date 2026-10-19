<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Mapper;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;

use function array_intersect_key;

/**
 * Combines the property filters of a hydrator, a property is only mapped if every filter keeps it.
 *
 * @internal
 */
final class ChainPropertyFilter implements PropertyFilter
{
    /** @param list<PropertyFilter> $filters */
    public function __construct(
        private readonly array $filters,
    ) {
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $context
     *
     * @return array<string, PropertyMetadata>|null
     *
     * @template T of object
     */
    public function filter(ClassMetadata $metadata, array $context): array|null
    {
        $result = null;

        foreach ($this->filters as $filter) {
            $fields = $filter->filter($metadata, $context);

            if ($fields === null) {
                continue;
            }

            $result = $result === null ? $fields : array_intersect_key($result, $fields);
        }

        return $result;
    }
}
