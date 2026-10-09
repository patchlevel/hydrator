<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography;

use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use ReflectionProperty;

use function array_key_exists;

final class CryptographyMetadataEnricher implements MetadataEnricher
{
    public function enrich(ClassMetadata $classMetadata): void
    {
        $subjectIdMapping = [];

        foreach ($classMetadata->properties as $property) {
            $isSubjectId = false;
            $attributeReflectionList = $property->reflection->getAttributes(DataSubjectId::class);

            if ($attributeReflectionList) {
                $subjectIdIdentifier = $attributeReflectionList[0]->newInstance()->name;

                if (array_key_exists($subjectIdIdentifier, $subjectIdMapping)) {
                    throw new DuplicateSubjectIdIdentifier(
                        $classMetadata->className,
                        $classMetadata->propertyForField($subjectIdMapping[$subjectIdIdentifier])->propertyName,
                        $property->propertyName,
                        $subjectIdIdentifier,
                    );
                }

                $subjectIdMapping[$subjectIdIdentifier] = $property->fieldName;

                $isSubjectId = true;
            }

            $sensitiveDataInfo = $this->sensitiveDataInfo($property->reflection);

            if (!$sensitiveDataInfo) {
                continue;
            }

            if ($isSubjectId) {
                throw new SubjectIdAndSensitiveDataConflict($classMetadata->className, $property->propertyName);
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
        $attributeReflectionList = $reflectionProperty->getAttributes(SensitiveData::class);

        if ($attributeReflectionList === []) {
            return null;
        }

        $attribute = $attributeReflectionList[0]->newInstance();

        return new SensitiveDataInfo(
            $attribute->subjectIdName,
            $attribute->fallbackCallable !== null ? ($attribute->fallbackCallable)(...) : $attribute->fallback,
        );
    }
}
