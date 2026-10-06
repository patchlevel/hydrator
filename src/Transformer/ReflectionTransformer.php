<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

use Patchlevel\Hydrator\CircularReference;
use Patchlevel\Hydrator\DenormalizationFailure;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\NormalizationFailure;
use Patchlevel\Hydrator\TypeMismatch;
use Throwable;
use TypeError;

use function array_key_exists;
use function array_values;
use function spl_object_id;

/**
 * Sets and reads the properties of the class with reflection, based on its metadata.
 *
 * @template T of object
 */
final class ReflectionTransformer implements ClassTransformer
{
    /** @param ClassMetadata<T> $metadata */
    public function __construct(
        private readonly ClassMetadata $metadata,
        private readonly CallStack $callStack = new CallStack(),
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     */
    public function hydrate(array $data, array $context): object
    {
        $metadata = $this->metadata;

        $object = $context[Hydrator::OBJECT_TO_POPULATE] ?? $metadata->newInstance();
        unset($context[Hydrator::OBJECT_TO_POPULATE]);

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
    public function extract(object $object, array $context): array
    {
        $objectId = spl_object_id($object);

        if (array_key_exists($objectId, $this->callStack->objects)) {
            $references = array_values($this->callStack->objects);
            $references[] = $object::class;

            throw new CircularReference($references);
        }

        $this->callStack->objects[$objectId] = $object::class;

        try {
            $data = [];

            foreach ($this->metadata->properties as $propertyMetadata) {
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
            unset($this->callStack->objects[$objectId]);
        }

        return $data;
    }
}
