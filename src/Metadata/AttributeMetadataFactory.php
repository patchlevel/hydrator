<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\Ignore;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Attribute\PersonalData;
use Patchlevel\Hydrator\Attribute\PostHydrate;
use Patchlevel\Hydrator\Attribute\PreExtract;
use Patchlevel\Hydrator\Guesser\BuiltInGuesser;
use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\ReflectionTypeAwareNormalizer;
use Patchlevel\Hydrator\Normalizer\TypeAwareNormalizer;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

use function array_key_exists;
use function array_merge;
use function array_values;

final class AttributeMetadataFactory implements MetadataFactory
{
    /** @var array<class-string, ClassMetadata> */
    private array $classMetadata = [];

    private readonly TypeResolver $typeResolver;

    private readonly Guesser|null $guesser;

    public function __construct(
        TypeResolver|null $typeResolver = null,
        Guesser|null $guesser = null,
    ) {
        $this->typeResolver = $typeResolver ?: TypeResolver::create();
        $this->guesser = $guesser ?: new BuiltInGuesser();
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

    /**
     * @param ReflectionClass<object> $reflectionClass
     *
     * @return list<PropertyMetadata>
     */
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

            if ($reflectionProperty->getDeclaringClass()->getName() !== $reflectionClass->getName()) {
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

            $properties[$fieldName] = new PropertyMetadata(
                $reflectionProperty,
                $fieldName,
                $this->getNormalizer($reflectionProperty),
                ...$this->getPersonalData($reflectionProperty),
            );
        }

        return array_values($properties);
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return list<CallbackMetadata>
     */
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

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return list<CallbackMetadata>
     */
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

    /** @param ReflectionClass<object> $reflectionClass */
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

    /** @return array{bool, mixed, (callable(string, mixed):mixed)|null} */
    private function getPersonalData(ReflectionProperty $reflectionProperty): array
    {
        $attributeReflectionList = $reflectionProperty->getAttributes(PersonalData::class);

        if ($attributeReflectionList === []) {
            return [false, null];
        }

        $attribute = $attributeReflectionList[0]->newInstance();

        return [true, $attribute->fallback, $attribute->fallbackCallable];
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

    private function getNormalizer(ReflectionProperty $reflectionProperty): Normalizer|null
    {
        $normalizer = $this->findNormalizerOnProperty($reflectionProperty);
        $type = null;

        if (!$normalizer) {
            $type = $this->typeResolver->resolve($reflectionProperty);
            $normalizer = $this->inferNormalizerByType($type);
        }

        if ($normalizer instanceof TypeAwareNormalizer) {
            $normalizer->handleType($type ?? $this->typeResolver->resolve($reflectionProperty));
        }

        if ($normalizer instanceof ReflectionTypeAwareNormalizer) {
            $normalizer->handleReflectionType($reflectionProperty->getType());
        }

        return $normalizer;
    }

    private function findNormalizerOnProperty(ReflectionProperty $reflectionProperty): Normalizer|null
    {
        $attributeReflectionList = $reflectionProperty->getAttributes(
            Normalizer::class,
            ReflectionAttribute::IS_INSTANCEOF,
        );

        if ($attributeReflectionList !== []) {
            return $attributeReflectionList[0]->newInstance();
        }

        return null;
    }

    private function inferNormalizerByType(Type $type): Normalizer|null
    {
        if ($type instanceof NullableType) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof ObjectType) {
            $normalizer = $this->findNormalizerOnClass($type->getClassName());

            if ($normalizer) {
                return $normalizer;
            }

            return $this->guesser->guess($type);
        }

        if ($type instanceof CollectionType) {
            $valueType = $type->getCollectionValueType();

            if ($valueType instanceof NullableType) {
                $valueType = $type->getWrappedType();
            }

            $normalizer = null;

            if ($valueType instanceof ObjectType) {
                $normalizer = $this->findNormalizerOnClass($valueType->getClassName());
            }

            if ($normalizer === null) {
                $normalizer = $this->inferNormalizerByType($valueType);
            }

            if ($normalizer) {
                return new ArrayNormalizer($normalizer);
            }

            return null;
        }

        return null;
    }

    /** @param class-string $class */
    private function findNormalizerOnClass(string $class): Normalizer|null
    {
        $reflectionClass = new ReflectionClass($class);

        $attributes = $reflectionClass->getAttributes(
            Normalizer::class,
            ReflectionAttribute::IS_INSTANCEOF,
        );

        if ($attributes !== []) {
            return $attributes[0]->newInstance();
        }

        $parent = $reflectionClass->getParentClass();

        if ($parent) {
            $normalizer = $this->findNormalizerOnClass($parent->getName());

            if ($normalizer !== null) {
                return $normalizer;
            }
        }

        foreach ($reflectionClass->getInterfaces() as $interface) {
            $normalizer = $this->findNormalizerOnClass($interface->getName());

            if ($normalizer !== null) {
                return $normalizer;
            }
        }

        return null;
    }
}
