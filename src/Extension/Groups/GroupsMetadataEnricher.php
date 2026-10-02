<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Groups;

use Patchlevel\Hydrator\Extension\Groups\Attribute\Groups;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;

final class GroupsMetadataEnricher implements MetadataEnricher
{
    public function enrich(ClassMetadata $classMetadata): void
    {
        $hasGroups = false;

        foreach ($classMetadata->properties as $property) {
            $attributeReflectionList = $property->reflection->getAttributes(Groups::class);

            if ($attributeReflectionList === []) {
                continue;
            }

            $property->extras[Groups::class] = $attributeReflectionList[0]->newInstance()->groups;
            $hasGroups = true;
        }

        if (!$hasGroups) {
            return;
        }

        // lets the middleware skip the filtering for classes without any groups
        $classMetadata->extras[Groups::class] = true;
    }
}
