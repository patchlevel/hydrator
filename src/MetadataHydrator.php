<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;
use Patchlevel\Hydrator\Normalizer\HydratorAwareNormalizer;
use Patchlevel\Hydrator\Normalizer\ServiceResolverNormalizer;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\NormalizerConfig;
use Psr\Container\ContainerInterface;
use ReflectionParameter;
use Throwable;
use TypeError;
use WeakMap;

use function array_key_exists;
use function array_values;
use function spl_object_id;

final class MetadataHydrator implements Hydrator
{
    /** @var array<int, class-string> */
    private array $stack = [];

    /**
     * @var WeakMap<NormalizerConfig, ServiceResolverNormalizer>
     */
    private WeakMap $serviceResolverNormalizerMap;

    public function __construct(
        private readonly MetadataFactory $metadataFactory = new AttributeMetadataFactory(),
        private readonly ContainerInterface|null $container = null,
    ) {
        $this->serviceResolverNormalizerMap = new WeakMap();
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

        $constructorParameters = null;

        foreach ($metadata->properties() as $propertyMetadata) {
            if (!array_key_exists($propertyMetadata->fieldName(), $data)) {
                if (!$propertyMetadata->reflection()->isPromoted()) {
                    continue;
                }

                if ($constructorParameters === null) {
                    $constructorParameters = $this->promotedConstructorParametersWithDefaultValue($metadata);
                }

                if (!array_key_exists($propertyMetadata->propertyName(), $constructorParameters)) {
                    continue;
                }

                /** @psalm-suppress MixedAssignment */
                $defaultValue = $constructorParameters[$propertyMetadata->propertyName()]->getDefaultValue();
                $propertyMetadata->setValue($object, $defaultValue);

                continue;
            }

            /** @psalm-suppress MixedAssignment */
            $value = $data[$propertyMetadata->fieldName()];
            $normalizer = $this->normalizer($propertyMetadata);

            if ($normalizer) {
                if ($normalizer instanceof HydratorAwareNormalizer) {
                    $normalizer->setHydrator($this);
                }

                try {
                    /** @psalm-suppress MixedAssignment */
                    $value = $normalizer->denormalize($value);
                } catch (Throwable $e) {
                    throw new DenormalizationFailure(
                        $class,
                        $propertyMetadata->propertyName(),
                        $normalizer::class,
                        $e,
                    );
                }
            }

            try {
                $propertyMetadata->setValue($object, $value);
            } catch (TypeError $e) {
                throw new TypeMismatch(
                    $class,
                    $propertyMetadata->propertyName(),
                    $e,
                );
            }
        }

        return $object;
    }

    /** @return array<string, mixed> */
    public function extract(object $object): array
    {
        $objectId = spl_object_id($object);

        if (array_key_exists($objectId, $this->stack)) {
            $references = array_values($this->stack);
            $references[] = $object::class;

            throw new CircularReference($references);
        }

        $this->stack[$objectId] = $object::class;

        try {
            $metadata = $this->metadataFactory->metadata($object::class);

            $data = [];

            foreach ($metadata->properties() as $propertyMetadata) {
                /** @psalm-suppress MixedAssignment */
                $value = $propertyMetadata->getValue($object);
                $normalizer = $this->normalizer($propertyMetadata);

                if ($normalizer) {
                    if ($normalizer instanceof HydratorAwareNormalizer) {
                        $normalizer->setHydrator($this);
                    }

                    try {
                        /** @psalm-suppress MixedAssignment */
                        $value = $normalizer->normalize($value);
                    } catch (CircularReference $e) {
                        throw $e;
                    } catch (Throwable $e) {
                        throw new NormalizationFailure(
                            $object::class,
                            $propertyMetadata->propertyName(),
                            $normalizer::class,
                            $e,
                        );
                    }
                }

                /** @psalm-suppress MixedAssignment */
                $data[$propertyMetadata->fieldName()] = $value;
            }

            return $data;
        } finally {
            unset($this->stack[$objectId]);
        }
    }

    /** @return array<string, ReflectionParameter> */
    private function promotedConstructorParametersWithDefaultValue(ClassMetadata $metadata): array
    {
        $constructor = $metadata->reflection()->getConstructor();

        if (!$constructor) {
            return [];
        }

        $parameters = $constructor->getParameters();
        $result = [];

        foreach ($parameters as $parameter) {
            if (!$parameter->isPromoted()) {
                continue;
            }

            if (!$parameter->isDefaultValueAvailable()) {
                continue;
            }

            $result[$parameter->getName()] = $parameter;
        }

        return $result;
    }

    private function normalizer(PropertyMetadata $propertyMetadata): Normalizer|null
    {
        $normalizer = $propertyMetadata->normalizer();

        if ($normalizer instanceof NormalizerConfig) {
            $normalizer = $this->serviceResolverNormalizerMap[$normalizer]
                ??= new ServiceResolverNormalizer($normalizer, $this->container);
        }

        return $normalizer;
    }
}
