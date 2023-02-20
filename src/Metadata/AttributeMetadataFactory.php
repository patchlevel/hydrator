<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

use function array_key_exists;
use function array_values;

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

        $reflectionClass = new ReflectionClass($class);

        return $this->getClassMetadata($reflectionClass);
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
            $this->getPropertyMetadataList($reflectionClass)
        );

        $parentMetadataClass = $reflectionClass->getParentClass();

        if ($parentMetadataClass) {
            $metadata = $this->mergeMetadata(
                $metadata,
                $this->getClassMetadata($parentMetadataClass)
            );
        }

        $this->classMetadata[$class] = $metadata;

        return $metadata;
    }

    /**
     * @return list<PropertyMetadata>
     */
    private function getPropertyMetadataList(ReflectionClass $reflectionClass): array
    {
        $properties = [];

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $fieldName = $this->getFieldName($reflectionProperty);

            if (array_key_exists($fieldName, $properties)) {
                throw DuplicatedFieldNameInMetadata::inClass(
                    $fieldName,
                    $reflectionClass->getName(),
                    $properties[$fieldName]->propertyName(),
                    $reflectionProperty->getName()
                );
            }

            $properties[$fieldName] = new PropertyMetadata(
                $reflectionProperty,
                $fieldName,
                $this->getNormalizer($reflectionProperty)
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

        $attribute = $attributeReflectionList[0]->newInstance();

        return $attribute->name();
    }

    private function getNormalizer(ReflectionProperty $reflectionProperty): ?Normalizer
    {
        $attributeReflectionList = $reflectionProperty->getAttributes(
            Normalizer::class,
            ReflectionAttribute::IS_INSTANCEOF
        );

        if ($attributeReflectionList !== []) {
            return $attributeReflectionList[0]->newInstance();
        }

        return null;
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
                throw DuplicatedFieldNameInMetadata::byInheritance($property->fieldName(), $parent->className(), $child->className());
            }

            $properties[$property->fieldName()] = $property;
        }

        return new ClassMetadata(
            $parent->reflection(),
            array_values($properties)
        );
    }
}
