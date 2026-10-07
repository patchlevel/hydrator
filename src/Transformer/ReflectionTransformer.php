<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

use Patchlevel\Hydrator\CircularReference;
use Patchlevel\Hydrator\DenormalizationFailure;
use Patchlevel\Hydrator\Handler\ExtractHandler;
use Patchlevel\Hydrator\Handler\HydrateHandler;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\NestedObject;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;
use Patchlevel\Hydrator\NormalizationFailure;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\TypeMismatch;
use Throwable;
use TypeError;

use function array_key_exists;
use function array_values;
use function assert;
use function is_array;
use function is_object;
use function spl_object_id;

/**
 * Sets and reads the properties of the class with reflection, based on its metadata.
 *
 * @template T of object
 */
final class ReflectionTransformer implements ClassTransformer
{
    /** @var array<string, NestedObject>|null property name => nested object, resolved on the first call */
    private array|null $nested = null;

    /** @var array<string, ClassTransformer|HydrateHandler> property name => handler of the nested class */
    private array $hydrateHandlers = [];

    /** @var array<string, ClassTransformer|ExtractHandler> property name => handler of the nested class */
    private array $extractHandlers = [];

    /**
     * @param ClassMetadata<T>         $metadata
     * @param TransformerResolver|null $resolver of the hydrator, nested objects of its calls are mapped with the
     *                                           handlers of their classes directly
     */
    public function __construct(
        private readonly ClassMetadata $metadata,
        private readonly CallStack $callStack = new CallStack(),
        private readonly TransformerResolver|null $resolver = null,
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

        if (isset($context[Hydrator::OBJECT_TO_POPULATE])) {
            $object = $context[Hydrator::OBJECT_TO_POPULATE];
            // only unset if it is there, unset() copies the context even if the key does not exist
            unset($context[Hydrator::OBJECT_TO_POPULATE]);
        } else {
            $object = $metadata->newInstance();
        }

        $constructorParameters = null;
        $nested = $this->nested($context);

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
                    $value = isset($nested[$propertyMetadata->propertyName])
                        ? $this->hydrateNested($propertyMetadata, $nested[$propertyMetadata->propertyName], $propertyMetadata->normalizer, $data[$propertyMetadata->fieldName], $context)
                        : $propertyMetadata->normalizer->denormalize($data[$propertyMetadata->fieldName], $context);
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
            $nested = $this->nested($context);

            foreach ($this->metadata->properties as $propertyMetadata) {
                if ($propertyMetadata->normalizer) {
                    try {
                        /** @psalm-suppress MixedAssignment */
                        $data[$propertyMetadata->fieldName] = isset($nested[$propertyMetadata->propertyName])
                            ? $this->extractNested($propertyMetadata, $nested[$propertyMetadata->propertyName], $propertyMetadata->normalizer, $propertyMetadata->getValue($object), $context)
                            : $propertyMetadata->normalizer->normalize($propertyMetadata->getValue($object), $context);
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

    /**
     * The nested objects which are mapped with the handlers of their classes directly. Only for calls of the
     * hydrator which created this transformer: a hydrator which wraps it has to see the nested objects.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, NestedObject>
     */
    private function nested(array $context): array
    {
        if ($this->resolver === null) {
            return [];
        }

        if ($this->nested === null) {
            $this->nested = [];

            foreach ($this->metadata->properties as $property) {
                $nested = $property->nested();

                if ($nested === null) {
                    continue;
                }

                $this->nested[$property->propertyName] = $nested;
            }
        }

        if ($this->nested === [] || ($context[Hydrator::HYDRATOR] ?? null) !== $this->resolver->hydrator()) {
            return [];
        }

        return $this->nested;
    }

    /** @param array<string, mixed> $context */
    private function hydrateNested(PropertyMetadata $property, NestedObject $nested, Normalizer $normalizer, mixed $value, array $context): mixed
    {
        // null and wrong types are handled by the normalizer
        if (!is_array($value) || $this->resolver === null) {
            return $normalizer->denormalize($value, $context);
        }

        $handler = $this->hydrateHandlers[$property->propertyName]
            ??= $this->resolver->hydrateHandler($nested->className);

        if (!$nested->list) {
            return $handler->hydrate($value, $context);
        }

        assert($normalizer instanceof ArrayNormalizer);
        $inner = $normalizer->innerNormalizer();
        $items = [];

        foreach ($value as $key => $item) {
            $items[$key] = is_array($item) ? $handler->hydrate($item, $context) : $inner->denormalize($item, $context);
        }

        return $items;
    }

    /** @param array<string, mixed> $context */
    private function extractNested(PropertyMetadata $property, NestedObject $nested, Normalizer $normalizer, mixed $value, array $context): mixed
    {
        if ($this->resolver === null) {
            return $normalizer->normalize($value, $context);
        }

        if (!$nested->list) {
            // subclasses are extracted with the handler of their own class by the hydrator
            if (!is_object($value) || $value::class !== $nested->className) {
                return $normalizer->normalize($value, $context);
            }

            $handler = $this->extractHandlers[$property->propertyName]
                ??= $this->resolver->extractHandler($nested->className);

            return $handler->extract($value, $context);
        }

        if (!is_array($value)) {
            return $normalizer->normalize($value, $context);
        }

        assert($normalizer instanceof ArrayNormalizer);
        $inner = $normalizer->innerNormalizer();
        $handler = null;
        $items = [];

        foreach ($value as $key => $item) {
            if (is_object($item) && $item::class === $nested->className) {
                $handler ??= $this->extractHandlers[$property->propertyName]
                    ??= $this->resolver->extractHandler($nested->className);
                $items[$key] = $handler->extract($item, $context);

                continue;
            }

            $items[$key] = $inner->normalize($item, $context);
        }

        return $items;
    }
}
