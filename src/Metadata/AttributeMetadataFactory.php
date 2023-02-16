<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

use function array_key_exists;

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

        $this->classMetadata[$class] = new ClassMetadata(
            $reflectionClass,
            $this->getPropertyMetadataList($reflectionClass)
        );

        return $this->classMetadata[$class];
    }

    /**
     * @return array<string, PropertyMetadata>
     */
    private function getPropertyMetadataList(ReflectionClass $reflectionClass): array
    {
        $properties = [];

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $properties[$reflectionProperty->getName()] = new PropertyMetadata(
                $reflectionProperty,
                $this->getFieldName($reflectionProperty),
                $this->getNormalizer($reflectionProperty)
            );
        }

        return $properties;
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
}
