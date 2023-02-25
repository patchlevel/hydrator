<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Throwable;
use TypeError;

final class MetadataHydrator implements Hydrator
{
    public function __construct(
        private readonly MetadataFactory $metadataFactory = new AttributeMetadataFactory()
    ) {
    }

    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $data
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(string $class, array $data): object
    {
        $metadata = $this->metadataFactory->metadata($class);
        $object = $metadata->newInstance();

        foreach ($metadata->properties() as $propertyMetadata) {
            /** @psalm-suppress MixedAssignment */
            $value = $data[$propertyMetadata->fieldName()] ?? null;

            $normalizer = $propertyMetadata->normalizer();

            if ($normalizer) {
                try {
                    /** @psalm-suppress MixedAssignment */
                    $value = $normalizer->denormalize($value);
                } catch (Throwable $e) {
                    throw new DenormalizationFailure(
                        $class,
                        $propertyMetadata->propertyName(),
                        $normalizer::class,
                        $e
                    );
                }
            }

            try {
                $propertyMetadata->setValue($object, $value);
            } catch (TypeError $e) {
                throw new TypeMismatch(
                    $class,
                    $propertyMetadata->propertyName(),
                    $e
                );
            }
        }

        return $object;
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(object $object): array
    {
        $metadata = $this->metadataFactory->metadata($object::class);

        $data = [];

        foreach ($metadata->properties() as $propertyMetadata) {
            /** @psalm-suppress MixedAssignment */
            $value = $propertyMetadata->getValue($object);

            $normalizer = $propertyMetadata->normalizer();

            if ($normalizer) {
                try {
                    /** @psalm-suppress MixedAssignment */
                    $value = $normalizer->normalize($value);
                } catch (Throwable $e) {
                    throw new NormalizationFailure(
                        $object::class,
                        $propertyMetadata->propertyName(),
                        $normalizer::class,
                        $e
                    );
                }
            }

            /** @psalm-suppress MixedAssignment */
            $data[$propertyMetadata->fieldName()] = $value;
        }

        return $data;
    }
}
