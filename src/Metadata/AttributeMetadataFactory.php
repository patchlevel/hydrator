<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\Ignore;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Attribute\PersonalData;
use Patchlevel\Hydrator\Attribute\PostHydrate;
use Patchlevel\Hydrator\Attribute\PreExtract;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeZoneNormalizer;
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\ReflectionTypeAwareNormalizer;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

use function array_key_exists;
use function array_merge;
use function array_values;
use function class_exists;
use function is_a;

final class AttributeMetadataFactory implements MetadataFactory
{
    /** @var array<class-string, ClassMetadata> */
    private array $classMetadata = [];

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

        try {
            $reflectionClass = new ReflectionClass($class);
        } catch (ReflectionException) {
            throw new ClassNotFound($class);
        }

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
            $this->getPostHydrateCallbacks($reflectionClass),
            $this->getPreExtractCallbacks($reflectionClass),
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

    /** @return list<CallbackMetadata> */
    private function getPostHydrateCallbacks(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods() as $reflectionMethod) {
            if ($reflectionMethod->isStatic()) {
                continue;
            }

            $attributeReflectionList = $reflectionMethod->getAttributes(PostHydrate::class);

            if ($attributeReflectionList === []) {
                continue;
            }

            $methods[] = new CallbackMetadata($reflectionMethod);
        }

        return $methods;
    }

    /** @return list<CallbackMetadata> */
    private function getPreExtractCallbacks(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods() as $reflectionMethod) {
            if ($reflectionMethod->isStatic()) {
                continue;
            }

            $attributeReflectionList = $reflectionMethod->getAttributes(PreExtract::class);

            if ($attributeReflectionList === []) {
                continue;
            }

            $methods[] = new CallbackMetadata($reflectionMethod);
        }

        return $methods;
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

        $normalizer = match ($className) {
            DateTimeImmutable::class => new DateTimeImmutableNormalizer(),
            DateTime::class => new DateTimeNormalizer(),
            DateTimeZone::class => new DateTimeZoneNormalizer(),
            default => null,
        };

        if ($normalizer) {
            return $normalizer;
        }

        if (is_a($className, BackedEnum::class, true)) {
            return new EnumNormalizer($className);
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
            array_merge($parent->postHydrateCallbacks(), $child->postHydrateCallbacks()),
            array_merge($parent->preExtractCallbacks(), $child->preExtractCallbacks()),
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
