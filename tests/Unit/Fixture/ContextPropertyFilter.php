<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Mapper\PropertyFilter;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;

use function in_array;
use function is_array;

/** Keeps the fields the context lists for a class, for example ['fields' => [Skill::class => ['name']]]. */
final class ContextPropertyFilter implements PropertyFilter
{
    public const FIELDS = 'fields';

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
        $classes = $context[self::FIELDS] ?? null;

        if (!is_array($classes)) {
            return null;
        }

        $fields = $classes[$metadata->className] ?? null;

        if (!is_array($fields)) {
            return null;
        }

        $result = [];

        foreach ($metadata->properties as $property) {
            if (!in_array($property->fieldName, $fields, true)) {
                continue;
            }

            $result[$property->fieldName] = $property;
        }

        return $result;
    }
}
