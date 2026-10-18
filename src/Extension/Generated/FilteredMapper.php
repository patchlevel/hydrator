<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Mapper\PropertyFilter;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;

/**
 * Applies the property filter of the hydrator to a generated mapper. The factory only wraps the generated mappers of
 * hydrators with a property filter, so the generated code of all other hydrators does not have to check for it.
 *
 * @internal
 *
 * @template T of object
 * @implements Mapper<T>
 */
final class FilteredMapper implements Mapper, NestedMapper
{
    /** @param GeneratedMapper&Mapper<T> $mapper */
    public function __construct(
        public readonly GeneratedMapper $mapper,
        private readonly PropertyFilter $filter,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     */
    public function hydrate(array $data, array $context): object
    {
        $fields = $this->filter->filter($this->mapper->metadata, $context);

        if ($fields !== null) {
            $data = self::withoutFilteredFields($this->mapper->metadata, $data, $fields);
        }

        return $this->mapper->hydrate($data, $context);
    }

    /**
     * @param T                    $object
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extract(object $object, array $context): array
    {
        $fields = $this->filter->filter($this->mapper->metadata, $context);

        if ($fields === null) {
            return $this->mapper->extract($object, $context);
        }

        if (!$this->mapper->ready) {
            $this->mapper->init();
        }

        // nested objects are only mapped right away for calls of the hydrator which created the mapper
        return $this->mapper->extractFiltered(
            $object,
            $context,
            $fields,
            ($context[Hydrator::HYDRATOR] ?? null) === $this->mapper->hydrator,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    public function hydrateNested(array $data, array $context): object
    {
        $fields = $this->filter->filter($this->mapper->metadata, $context);

        if ($fields !== null) {
            $data = self::withoutFilteredFields($this->mapper->metadata, $data, $fields);
        }

        return $this->mapper->hydrateNested($data, $context);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extractNested(object $object, array $context): array
    {
        $fields = $this->filter->filter($this->mapper->metadata, $context);

        if ($fields === null) {
            return $this->mapper->extractNested($object, $context);
        }

        return $this->mapper->extractFiltered($object, $context, $fields, true);
    }

    /**
     * Removes the fields of the properties the filter leaves out, also under their aliases, so they are treated as
     * missing. Unknown fields don't matter, they are not read anyway.
     *
     * @param ClassMetadata<object>           $metadata
     * @param array<string, mixed>            $data
     * @param array<string, PropertyMetadata> $fields   the properties to map by field name
     *
     * @return array<string, mixed>
     */
    private static function withoutFilteredFields(ClassMetadata $metadata, array $data, array $fields): array
    {
        foreach ($metadata->properties as $property) {
            if (isset($fields[$property->fieldName])) {
                continue;
            }

            unset($data[$property->fieldName]);

            foreach ($property->aliases as $alias) {
                unset($data[$alias]);
            }
        }

        return $data;
    }
}
