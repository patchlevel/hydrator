<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Groups;

use Patchlevel\Hydrator\Extension\Groups\Attribute\Groups;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;

use function array_intersect;
use function in_array;
use function is_array;
use function is_string;

final class GroupsMiddleware implements Middleware
{
    /** @var array<string, array{ClassMetadata, list<string>}> */
    private array $selections = [];

    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Stack $stack): object
    {
        if (!isset($context[GroupsExtension::GROUPS]) && !isset($context[GroupsExtension::IGNORED_GROUPS])) {
            return $stack->next()->hydrate($metadata, $data, $context, $stack);
        }

        foreach ($this->selection($metadata, $context)[1] as $fieldName) {
            unset($data[$fieldName]);
        }

        return $stack->next()->hydrate($metadata, $data, $context, $stack);
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param T                    $object
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     *
     * @template T of object
     */
    public function extract(ClassMetadata $metadata, object $object, array $context, Stack $stack): array
    {
        if (!isset($context[GroupsExtension::GROUPS]) && !isset($context[GroupsExtension::IGNORED_GROUPS])) {
            return $stack->next()->extract($metadata, $object, $context, $stack);
        }

        return $stack->next()->extract($this->selection($metadata, $context)[0], $object, $context, $stack);
    }

    /**
     * Returns the filtered metadata and the excluded field names.
     *
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $context
     *
     * @return array{ClassMetadata<T>, list<string>}
     *
     * @template T of object
     */
    private function selection(ClassMetadata $metadata, array $context): array
    {
        $groups = $context[GroupsExtension::GROUPS] ?? null;

        // ignored groups can't match a class without any groups
        if ($groups === null && !isset($metadata->extras[Groups::class])) {
            return [$metadata, []];
        }

        $ignoredGroups = $context[GroupsExtension::IGNORED_GROUPS] ?? null;
        $key = $metadata->className . "\0" . $this->key($groups) . "\0" . $this->key($ignoredGroups);

        /** @var array{ClassMetadata<T>, list<string>} $selection */
        $selection = $this->selections[$key] ??= $this->createSelection(
            $metadata,
            $this->normalize($groups),
            $this->normalize($ignoredGroups),
        );

        return $selection;
    }

    /**
     * @param ClassMetadata<T>  $metadata
     * @param list<string>|null $groups
     * @param list<string>|null $ignoredGroups
     *
     * @return array{ClassMetadata<T>, list<string>}
     *
     * @template T of object
     */
    private function createSelection(ClassMetadata $metadata, array|null $groups, array|null $ignoredGroups): array
    {
        if ($groups !== null && in_array(GroupsExtension::ALL, $groups, true)) {
            $groups = null;
        }

        if ($groups === null && $ignoredGroups === null) {
            return [$metadata, []];
        }

        $properties = [];
        $excludedFields = [];

        foreach ($metadata->properties as $propertyMetadata) {
            /** @var list<string> $propertyGroups */
            $propertyGroups = $propertyMetadata->extras[Groups::class] ?? [];

            if (
                ($groups !== null && array_intersect($propertyGroups, $groups) === [])
                || ($ignoredGroups !== null && array_intersect($propertyGroups, $ignoredGroups) !== [])
            ) {
                $excludedFields[] = $propertyMetadata->fieldName;

                continue;
            }

            $properties[] = $propertyMetadata;
        }

        if ($excludedFields === []) {
            return [$metadata, []];
        }

        return [
            new ClassMetadata(
                $metadata->reflection,
                $metadata->normalizer,
                $properties,
                $metadata->lazy,
                $metadata->extras,
            ),
            $excludedFields,
        ];
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
