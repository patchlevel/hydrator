<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\Ignore;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Attribute\PersonalData;
use Patchlevel\Hydrator\Guesser\BuiltInGuesser;
use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\ReflectionTypeAwareNormalizer;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

use function array_key_exists;
use function array_values;
use function class_exists;

final class AttributeMetadataFactory implements MetadataFactory
{
    /** @var array<class-string, ClassMetadata> */
    private array $classMetadata = [];

    /** @param iterable<Guesser> $guessers */
    public function __construct(
        private readonly iterable $guessers = [
            new BuiltInGuesser(),
        ],
    ) {
    }

    /**
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     *
     * @template T of object
     */
    public function metadata(string $class): ClassMetadata
    {
        if (array_key_exists($class, $this->classMetadata)) {
            /** @var ClassMetadata<T> $classMetadata */
            $classMetadata = $this->classMetadata[$class];

            return $classMetadata;
        }

        $reflectionClass = new ReflectionClass($class);

        $classMetadata = $this->getClassMetadata($reflectionClass);

        $this->validate($classMetadata);

        return $classMetadata;
    }

    /**
     * @param ReflectionClass<T> $reflectionClass
     *
     * @return ClassMetadata<T>
     *
     * @template T of object
     */
    private function getClassMetadata(ReflectionClass $reflectionClass): ClassMetadata
    {
        $class = $reflectionClass->getName();

        if (array_key_exists($class, $this->classMetadata)) {
            /** @var ClassMetadata<T> $classMetadata */
            $classMetadata = $this->classMetadata[$class];

            return $classMetadata;
        }

        $metadata = new ClassMetadata(
            $reflectionClass,
            $this->getPropertyMetadataList($reflectionClass),
            $this->getSubjectIdField($reflectionClass),
        );

        $parentMetadataClass = $reflectionClass->getParentClass();

        if ($parentMetadataClass) {
            $metadata = $this->mergeMetadata(
                $metadata,
                $this->getClassMetadata($parentMetadataClass),
            );
        }

        $this->classMetadata[$class] = $metadata;

        return $metadata;
    }

    /** @return list<PropertyMetadata> */
    private function getPropertyMetadataList(ReflectionClass $reflectionClass): array
    {
        $properties = [];

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->isStatic()) {
                continue;
            }

            if ($this->hasIgnore($reflectionProperty)) {
                continue;
            }

            $fieldName = $this->getFieldName($reflectionProperty);

            if (array_key_exists($fieldName, $properties)) {
                throw DuplicatedFieldNameInMetadata::inClass(
                    $fieldName,
                    $reflectionClass->getName(),
                    $properties[$fieldName]->propertyName(),
                    $reflectionProperty->getName(),
                );
            }

            [$isPersonalData, $personalDataFallback] = $this->getPersonalData($reflectionProperty);

            $properties[$fieldName] = new PropertyMetadata(
                $reflectionProperty,
                $fieldName,
                $this->getNormalizer($reflectionProperty),
                $isPersonalData,
                $personalDataFallback,
            );
        }

        return array_values($properties);
    }

    private function getFieldName(ReflectionProperty $reflectionProperty): string
    {
        $attributeReflectionList = $reflectionProperty->getAttributes(NormalizedName::class);

        if ($attributeReflectionList === []) {
            return $reflectionProperty->getName();
        }

        return $attributeReflectionList[0]->newInstance()->name();
    }

    private function getNormalizer(ReflectionProperty $reflectionProperty): Normalizer|null
    {
        $normalizer = $this->findNormalizer($reflectionProperty);

        if (!$normalizer) {
            $normalizer = $this->inferNormalizer($reflectionProperty);
        }

        if ($normalizer instanceof ReflectionTypeAwareNormalizer) {
            $reflectionPropertyType = $reflectionProperty->getType();
            $normalizer->handleReflectionType($reflectionPropertyType);
        }

        return $normalizer;
    }

    private function inferNormalizer(ReflectionProperty $property): Normalizer|null
    {
        $type = $property->getType();

        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

        $className = $type->getName();

        if (!class_exists($className)) {
            return null;
        }

        foreach ($this->guessers as $guesser) {
            $normalizer = $guesser->guess($className);

            if ($normalizer !== null) {
                return $normalizer;
            }
        }

        return null;
    }

    private function hasIgnore(ReflectionProperty $reflectionProperty): bool
    {
        return $reflectionProperty->getAttributes(Ignore::class) !== [];
    }

    /**
     * @param ClassMetadata<T> $parent
     *
     * @return ClassMetadata<T>
     *
     * @template T of object
     */
    private function mergeMetadata(ClassMetadata $parent, ClassMetadata $child): ClassMetadata
    {
        $properties = [];

        foreach ($parent->properties() as $property) {
            $properties[$property->fieldName()] = $property;
        }

        foreach ($child->properties() as $property) {
            if (array_key_exists($property->fieldName(), $properties)) {
                throw DuplicatedFieldNameInMetadata::byInheritance(
                    $property->fieldName(),
                    $parent->className(),
                    $child->className(),
                );
            }

            $properties[$property->fieldName()] = $property;
        }

        $parentDataSubjectIdField = $parent->dataSubjectIdField();
        $childDataSubjectIdField = $child->dataSubjectIdField();

        if ($parentDataSubjectIdField !== null && $childDataSubjectIdField !== null) {
            $parentProperty = $parent->propertyForField($parentDataSubjectIdField);
            $childProperty = $child->propertyForField($childDataSubjectIdField);

            throw new MultipleDataSubjectId($parentProperty->propertyName(), $childProperty->propertyName());
        }

        return new ClassMetadata(
            $parent->reflection(),
            array_values($properties),
            $parentDataSubjectIdField ?? $childDataSubjectIdField,
        );
    }

    private function getSubjectIdField(ReflectionClass $reflectionClass): string|null
    {
        $property = null;

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $attributeReflectionList = $reflectionProperty->getAttributes(DataSubjectId::class);

            if (!$attributeReflectionList) {
                continue;
            }

            if ($property !== null) {
                throw new MultipleDataSubjectId($property->getName(), $reflectionProperty->getName());
            }

            $property = $reflectionProperty;
        }

        if ($property === null) {
            return null;
        }

        return $this->getFieldName($property);
    }

    /** @return array{bool, mixed} */
    private function getPersonalData(ReflectionProperty $reflectionProperty): array
    {
        $attributeReflectionList = $reflectionProperty->getAttributes(PersonalData::class);

        if ($attributeReflectionList === []) {
            return [false, null];
        }

        $attribute = $attributeReflectionList[0]->newInstance();

        return [true, $attribute->fallback];
    }

    private function validate(ClassMetadata $metadata): void
    {
        $hasPersonalData = false;

        foreach ($metadata->properties() as $property) {
            if ($property->isPersonalData()) {
                $hasPersonalData = true;
            }

            if ($property->isPersonalData() && $metadata->dataSubjectIdField() === $property->fieldName()) {
                throw new SubjectIdAndPersonalDataConflict($metadata->className(), $property->propertyName());
            }
        }

        if ($hasPersonalData && $metadata->dataSubjectIdField() === null) {
            throw new MissingDataSubjectId($metadata->className());
        }
    }

    private function findNormalizer(ReflectionProperty $reflectionProperty): Normalizer|null
    {
        $attributeReflectionList = $reflectionProperty->getAttributes(
            Normalizer::class,
            ReflectionAttribute::IS_INSTANCEOF,
        );

        if ($attributeReflectionList !== []) {
            return $attributeReflectionList[0]->newInstance();
        }

        $type = $reflectionProperty->getType();

        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

        if (!class_exists($type->getName())) {
            return null;
        }

        return $this->findNormalizerOnClass(new ReflectionClass($type->getName()));
    }

    private function findNormalizerOnClass(ReflectionClass $reflectionClass): Normalizer|null
    {
        $attributes = $reflectionClass->getAttributes(
            Normalizer::class,
            ReflectionAttribute::IS_INSTANCEOF,
        );

        if ($attributes !== []) {
            return $attributes[0]->newInstance();
        }

        $parent = $reflectionClass->getParentClass();

        if ($parent) {
            $normalizer = $this->findNormalizerOnClass($parent);

            if ($normalizer !== null) {
                return $normalizer;
            }
        }

        foreach ($reflectionClass->getInterfaces() as $interface) {
            $normalizer = $this->findNormalizerOnClass($interface);

            if ($normalizer !== null) {
                return $normalizer;
            }
        }

        return null;
    }
}
