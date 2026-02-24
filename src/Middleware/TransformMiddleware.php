<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

use Patchlevel\Hydrator\CircularReference;
use Patchlevel\Hydrator\DenormalizationFailure;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\NormalizationFailure;
use Patchlevel\Hydrator\TypeMismatch;
use Throwable;
use TypeError;

use function array_key_exists;
use function array_values;
use function spl_object_id;

final class TransformMiddleware implements Middleware
{
    /** @var array<int, class-string> */
    private array $callStack = [];

    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Stack $stack): object
    {
        $object = $metadata->newInstance();

        foreach ($metadata->propertiesWithoutNormalizer as $propertyMetadata) {
            $fieldName = $propertyMetadata->fieldName;

            if (!isset($data[$fieldName]) && !array_key_exists($fieldName, $data)) {
                if (!$propertyMetadata->reflection->isPromoted()) {
                    goto next_without_normalizer;
                }

                $constructorParameters = $metadata->promotedConstructorDefaults();

                if (!isset($constructorParameters[$propertyMetadata->propertyName])) {
                    goto next_without_normalizer;
                }

                $propertyMetadata->reflection->setValue(
                    $object,
                    $constructorParameters[$propertyMetadata->propertyName]->getDefaultValue(),
                );

                goto next_without_normalizer;
            }

            try {
                $propertyMetadata->reflection->setValue($object, $data[$fieldName]);
            } catch (TypeError $e) {
                throw new TypeMismatch(
                    $metadata->className,
                    $propertyMetadata->propertyName,
                    $e,
                );
            }

            next_without_normalizer:
        }

        foreach ($metadata->propertiesWithNormalizer as $propertyMetadata) {
            $fieldName = $propertyMetadata->fieldName;

            if (!isset($data[$fieldName]) && !array_key_exists($fieldName, $data)) {
                if (!$propertyMetadata->reflection->isPromoted()) {
                    goto next_with_normalizer;
                }

                $constructorParameters = $metadata->promotedConstructorDefaults();

                if (!isset($constructorParameters[$propertyMetadata->propertyName])) {
                    goto next_with_normalizer;
                }

                $propertyMetadata->reflection->setValue(
                    $object,
                    $constructorParameters[$propertyMetadata->propertyName]->getDefaultValue(),
                );

                goto next_with_normalizer;
            }

            $normalizer = $propertyMetadata->normalizer;

            try {
                /** @psalm-suppress MixedAssignment */
                $value = $normalizer->denormalize($data[$fieldName], $context);
            } catch (Throwable $e) {
                throw new DenormalizationFailure(
                    $metadata->className,
                    $propertyMetadata->propertyName,
                    $normalizer::class,
                    $e,
                );
            }

            try {
                $propertyMetadata->reflection->setValue($object, $value);
            } catch (TypeError $e) {
                throw new TypeMismatch(
                    $metadata->className,
                    $propertyMetadata->propertyName,
                    $e,
                );
            }

            next_with_normalizer:
        }

        return $object;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extract(ClassMetadata $metadata, object $object, array $context, Stack $stack): array
    {
        $objectId = spl_object_id($object);

        if (isset($this->callStack[$objectId])) {
            $references = array_values($this->callStack);
            $references[] = $object::class;

            throw new CircularReference($references);
        }

        $this->callStack[$objectId] = $object::class;

        try {
            $data = [];

            foreach ($metadata->propertiesWithoutNormalizer as $propertyMetadata) {
                $data[$propertyMetadata->fieldName] = $propertyMetadata->reflection->getValue($object);
            }

            foreach ($metadata->propertiesWithNormalizer as $propertyMetadata) {
                $normalizer = $propertyMetadata->normalizer;

                try {
                    /** @psalm-suppress MixedAssignment */
                    $data[$propertyMetadata->fieldName] = $normalizer->normalize(
                        $propertyMetadata->reflection->getValue($object),
                        $context,
                    );
                } catch (Throwable $e) {
                    if ($e instanceof CircularReference) {
                        throw $e;
                    }

                    throw new NormalizationFailure(
                        $object::class,
                        $propertyMetadata->propertyName,
                        $normalizer::class,
                        $e,
                    );
                }
            }
        } finally {
            unset($this->callStack[$objectId]);
        }

        return $data;
    }
}
