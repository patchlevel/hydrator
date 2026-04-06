<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography;

use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\PersonalData;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use ReflectionProperty;

use function array_key_exists;
use function in_array;

final class LegacyCryptographyMetadataEnricher implements MetadataEnricher
{
    private const SUBJECT_ID = 'legacy';

    public function enrich(ClassMetadata $classMetadata): void
    {
        /** @var array<string, string> $subjectIdMapping */
        $subjectIdMapping = isset($classMetadata->extras[SubjectIdFieldMapping::class])
            ? $classMetadata->extras[SubjectIdFieldMapping::class]->nameToField
            : [];

        foreach ($classMetadata->properties as $property) {
            $attributeReflectionList = $property->reflection->getAttributes(DataSubjectId::class);

            if ($attributeReflectionList) {
                if (array_key_exists(self::SUBJECT_ID, $subjectIdMapping)) {
                    throw new DuplicateSubjectIdIdentifier(
                        $classMetadata->className,
                        $classMetadata->propertyForField($subjectIdMapping[self::SUBJECT_ID])->propertyName,
                        $property->propertyName,
                        self::SUBJECT_ID,
                    );
                }

                $subjectIdMapping[self::SUBJECT_ID] = $property->fieldName;
            }

            $sensitiveDataInfo = $this->sensitiveDataInfo($property->reflection);

            if (!$sensitiveDataInfo) {
                continue;
            }

            if (in_array($property->fieldName, $subjectIdMapping, true)) {
                throw new SubjectIdAndSensitiveDataConflict($classMetadata->className, $property->propertyName);
            }

            if (isset($property->extras[SensitiveDataInfo::class])) {
                throw new PersonalDataAndSensitiveDataOnSameProperty($classMetadata->className, $property->propertyName);
            }

            $property->extras[SensitiveDataInfo::class] = $sensitiveDataInfo;
        }

        if ($subjectIdMapping === []) {
            return;
        }

        $classMetadata->extras[SubjectIdFieldMapping::class] = new SubjectIdFieldMapping($subjectIdMapping);
    }

    private function sensitiveDataInfo(ReflectionProperty $reflectionProperty): SensitiveDataInfo|null
    {
        $attributeReflectionList = $reflectionProperty->getAttributes(PersonalData::class);

        if ($attributeReflectionList === []) {
            return null;
        }

        $attribute = $attributeReflectionList[0]->newInstance();

        return new SensitiveDataInfo(
            self::SUBJECT_ID,
            $attribute->fallbackCallable !== null ? ($attribute->fallbackCallable)(...) : $attribute->fallback,
        );
    }
}
