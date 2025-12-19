<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\SensitiveData;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataFactory;

use function array_key_exists;

final class CryptographyMetadataFactory implements MetadataFactory
{
    public function __construct(
        private readonly Cryptographer $cryptographer,
        private readonly MetadataFactory $metadataFactory,
    ) {
    }

    public function metadata(string $class): ClassMetadata
    {
        $metadata = $this->metadataFactory->metadata($class);

        $subjectIdMapping = [];

        foreach ($metadata->properties as $property) {
            $isSubjectId = false;
            $attributeReflectionList = $property->reflection->getAttributes(DataSubjectId::class);

            if ($attributeReflectionList) {
                $subjectIdIdentifier = $attributeReflectionList[0]->newInstance()->name;

                if (array_key_exists($subjectIdIdentifier, $subjectIdMapping)) {
                    throw new DuplicateSubjectIdIdentifier(
                        $metadata->className,
                        $metadata->propertyForField($subjectIdMapping[$subjectIdIdentifier])->propertyName,
                        $property->propertyName,
                        $subjectIdIdentifier,
                    );
                }

                $subjectIdMapping[$subjectIdIdentifier] = $property->fieldName;

                $isSubjectId = true;
            }

            $attributeReflectionList = $property->reflection->getAttributes(SensitiveData::class);

            if ($attributeReflectionList === []) {
                continue;
            }

            if ($isSubjectId) {
                throw new SubjectIdAndSensitiveDataConflict($metadata->className, $property->propertyName);
            }

            $attribute = $attributeReflectionList[0]->newInstance();

            $property->normalizer = new CryptoNormalizer(
                $this->cryptographer,
                $attribute->subjectIdName,
                $attribute->fallbackCallable !== null ? ($attribute->fallbackCallable)(...) : $attribute->fallback,
                $property->normalizer,
            );
        }

        if ($subjectIdMapping !== []) {
            $metadata->extras[SubjectIdFieldMapping::class] = new SubjectIdFieldMapping($subjectIdMapping);
        }

        return $metadata;
    }
}
