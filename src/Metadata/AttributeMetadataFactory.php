<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use Patchlevel\Hydrator\Attribute\Ignore;
use Patchlevel\Hydrator\Attribute\Lazy;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Attribute\PostHydrate;
use Patchlevel\Hydrator\Attribute\PreExtract;
use Patchlevel\Hydrator\Guesser\BuiltInGuesser;
use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\ArrayShapeNormalizer;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\TypeAwareNormalizer;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\TemplateType;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

use function array_key_exists;
use function array_merge;
use function array_values;

final class AttributeMetadataFactory implements MetadataFactory
{
    private readonly TypeResolver $typeResolver;

    private readonly Guesser $guesser;

    public function __construct(
        TypeResolver|null $typeResolver = null,
        Guesser|null $guesser = null,
    ) {
        $this->typeResolver = $typeResolver ?: TypeResolver::create();
        $this->guesser = $guesser ?: new BuiltInGuesser(false);
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
        try {
            $reflectionClass = new ReflectionClass($class);
        } catch (ReflectionException) {
            throw new ClassNotFound($class);
        }

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
        $metadata = new ClassMetadata(
            $reflectionClass,
            $this->getPropertyMetadataList($reflectionClass),
            $this->getPostHydrateCallbacks($reflectionClass),
            $this->getPreExtractCallbacks($reflectionClass),
            $this->getLazy($reflectionClass),
        );

        $parentMetadataClass = $reflectionClass->getParentClass();

        if ($parentMetadataClass) {
            $metadata = $this->mergeMetadata(
                $metadata,
                $this->getClassMetadata($parentMetadataClass),
            );
        }

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

    /** @param ReflectionClass<object> $reflection */
    private function getLazy(ReflectionClass $reflection): bool|null
    {
        $attributeReflectionList = $reflection->getAttributes(Lazy::class);

        if ($attributeReflectionList === []) {
            return null;
        }

        return $attributeReflectionList[0]->newInstance()->enabled;
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

        foreach ($parent->properties as $property) {
            $properties[$property->fieldName] = $property;
        }

        foreach ($child->properties as $property) {
            if (array_key_exists($property->fieldName, $properties)) {
                throw DuplicatedFieldNameInMetadata::byInheritance(
                    $property->fieldName,
                    $parent->className(),
                    $child->className(),
                );
            }

            $properties[$property->fieldName] = $property;
        }

        return new ClassMetadata(
            $parent->reflection,
            array_values($properties),
            array_merge($parent->postHydrateCallbacks, $child->postHydrateCallbacks),
            array_merge($parent->preExtractCallbacks, $child->preExtractCallbacks),
            $child->lazy ?? $parent->lazy,
            array_merge($parent->extras, $child->extras),
        );
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

        if ($type instanceof TemplateType) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof GenericType) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof ObjectType) {
            $normalizer = $this->findNormalizerOnClass($type->getClassName());

            if ($normalizer) {
                return $normalizer;
            }

            return $this->guesser->guess($type);
        }

        if ($type instanceof ArrayShapeType) {
            $shape = $type->getShape();

            $normalizers = [];

            foreach ($shape as $field => $fieldInfo) {
                $valueType = $fieldInfo['type'];

                if ($valueType instanceof NullableType) {
                    $valueType = $valueType->getWrappedType();
                }

                $normalizer = null;

                if ($valueType instanceof ObjectType) {
                    $normalizer = $this->findNormalizerOnClass($valueType->getClassName());
                }

                if ($normalizer === null) {
                    $normalizer = $this->inferNormalizerByType($valueType);
                }

                if ($normalizer === null) {
                    continue;
                }

                $normalizers[$field] = $normalizer;
            }

            if ($normalizers === []) {
                return null;
            }

            return new ArrayShapeNormalizer($normalizers);
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
