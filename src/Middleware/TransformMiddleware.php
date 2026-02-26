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

        $constructorParameters = null;

        foreach ($metadata->properties as $propertyMetadata) {
            if (!array_key_exists($propertyMetadata->fieldName, $data)) {
                if (!$propertyMetadata->reflection->isPromoted()) {
                    continue;
                }

                $constructorParameters ??= $metadata->promotedConstructorDefaults();

                if (!array_key_exists($propertyMetadata->propertyName, $constructorParameters)) {
                    continue;
                }

                $propertyMetadata->setValue(
                    $object,
                    $constructorParameters[$propertyMetadata->propertyName]->getDefaultValue(),
                );

                continue;
            }

            if ($propertyMetadata->normalizer) {
                try {
                    /** @psalm-suppress MixedAssignment */
                    $value = $propertyMetadata->normalizer->denormalize($data[$propertyMetadata->fieldName], $context);
                } catch (Throwable $e) {
                    throw new DenormalizationFailure(
                        $metadata->className,
                        $propertyMetadata->propertyName,
                        $propertyMetadata->normalizer::class,
                        $e,
                    );
                }
            } else {
                $value = $data[$propertyMetadata->fieldName];
            }

            try {
                $propertyMetadata->setValue($object, $value);
            } catch (TypeError $e) {
                throw new TypeMismatch(
                    $metadata->className,
                    $propertyMetadata->propertyName,
                    $e,
                );
            }
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

        if (array_key_exists($objectId, $this->callStack)) {
            $references = array_values($this->callStack);
            $references[] = $object::class;

            throw new CircularReference($references);
        }

        $this->callStack[$objectId] = $object::class;

        try {
            $data = [];

            foreach ($metadata->properties as $propertyMetadata) {
                if ($propertyMetadata->normalizer) {
                    try {
                        /** @psalm-suppress MixedAssignment */
                        $data[$propertyMetadata->fieldName] = $propertyMetadata->normalizer->normalize(
                            $propertyMetadata->getValue($object),
                            $context,
                        );
                    } catch (CircularReference $e) {
                        throw $e;
                    } catch (Throwable $e) {
                        throw new NormalizationFailure(
                            $object::class,
                            $propertyMetadata->propertyName,
                            $propertyMetadata->normalizer::class,
                            $e,
                        );
                    }
                } else {
                    $data[$propertyMetadata->fieldName] = $propertyMetadata->getValue($object);
                }
            }
        } finally {
            unset($this->callStack[$objectId]);
        }

        return $data;
    }
}
