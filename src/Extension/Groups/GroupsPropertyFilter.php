<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Groups;

use Patchlevel\Hydrator\Extension\Groups\Attribute\Groups;
use Patchlevel\Hydrator\Mapper\PropertyFilter;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;

use function array_intersect;
use function array_key_exists;
use function in_array;
use function is_array;
use function is_string;

/** Keeps the properties of the groups in the context, see {@see GroupsExtension}. */
final class GroupsPropertyFilter implements PropertyFilter
{
    /** @var array<class-string, array<string, array<string, PropertyMetadata>|null>> class => key of the groups => selection */
    private array $selections = [];

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
        if (!isset($context[GroupsExtension::GROUPS]) && !isset($context[GroupsExtension::IGNORED_GROUPS])) {
            return null;
        }

        $groups = $context[GroupsExtension::GROUPS] ?? null;

        // ignored groups can't match a class without any groups
        if ($groups === null && !isset($metadata->extras[Groups::class])) {
            return null;
        }

        $ignoredGroups = $context[GroupsExtension::IGNORED_GROUPS] ?? null;
        $key = $this->key($groups) . "\0" . $this->key($ignoredGroups);

        $selections = $this->selections[$metadata->className] ?? [];

        if (array_key_exists($key, $selections)) {
            return $selections[$key];
        }

        return $this->selections[$metadata->className][$key] = $this->select(
            $metadata,
            $this->normalize($groups),
            $this->normalize($ignoredGroups),
        );
    }

    /**
     * @param ClassMetadata<T>  $metadata
     * @param list<string>|null $groups
     * @param list<string>|null $ignoredGroups
     *
     * @return array<string, PropertyMetadata>|null null if no property is left out
     *
     * @template T of object
     */
    private function select(ClassMetadata $metadata, array|null $groups, array|null $ignoredGroups): array|null
    {
        if ($groups !== null && in_array(GroupsExtension::ALL, $groups, true)) {
            $groups = null;
        }

        $properties = [];
        $excluded = false;

        foreach ($metadata->properties as $propertyMetadata) {
            /** @var list<string> $propertyGroups */
            $propertyGroups = $propertyMetadata->extras[Groups::class] ?? [];

            if (
                ($groups !== null && array_intersect($propertyGroups, $groups) === [])
                || ($ignoredGroups !== null && array_intersect($propertyGroups, $ignoredGroups) !== [])
            ) {
                $excluded = true;

                continue;
            }

            $properties[$propertyMetadata->fieldName] = $propertyMetadata;
        }

        return $excluded ? $properties : null;
    }

    /**
     * Builds a cheap cache key from the raw context value, values with the same key normalize to the same groups.
     */
    private function key(mixed $groups): string
    {
        if (is_string($groups)) {
            return "\1" . $groups;
        }

        if (!is_array($groups)) {
            return '';
        }

        $key = '';

        foreach ($groups as $group) {
            if (!is_string($group)) {
                continue;
            }

            $key .= "\1" . $group;
        }

        return $key;
    }

    /** @return list<string>|null */
    private function normalize(mixed $groups): array|null
    {
        if (is_string($groups)) {
            return [$groups];
        }

        if (!is_array($groups)) {
            return null;
        }

        $result = [];

        foreach ($groups as $group) {
            if (!is_string($group)) {
                continue;
            }

            $result[] = $group;
        }

        return $result === [] ? null : $result;
    }
}
