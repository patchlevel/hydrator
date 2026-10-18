<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Mapper;

use Patchlevel\Hydrator\CircularReference;
use Patchlevel\Hydrator\DenormalizationFailure;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;
use Patchlevel\Hydrator\NormalizationFailure;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\TypeMismatch;
use Throwable;
use TypeError;

use function array_key_exists;
use function array_values;
use function class_exists;
use function is_array;
use function is_object;
use function spl_object_id;

/**
 * Sets and reads the properties of the class with reflection, based on its metadata.
 *
 * Nested objects of an {@see ObjectNormalizer}, directly or in an {@see ArrayNormalizer}, are mapped with the mapper of
 * their class right away, without the detour through the normalizer and the hydrator. This only happens for calls of
 * the hydrator which created this mapper: a hydrator which wraps it has to see the nested objects.
 *
 * The {@see PropertyFilter} of the hydrator decides which properties are mapped for a call.
 *
 * @template T of object
 * @implements Mapper<T>
 */
final class ReflectionMapper implements Mapper
{
    /** @var array<string, array{class-string, bool}>|null property name => [nested class, is a list], resolved on the first call */
    private array|null $nested = null;

    /** @var array<string, Mapper<object>|null> property name => mapper of the nested class */
    private array $nestedMappers = [];

    private readonly PropertyFilter|null $filter;

    /** @param ClassMetadata<T> $metadata */
    public function __construct(
        private readonly ClassMetadata $metadata,
        private readonly StackHydrator|null $hydrator = null,
        private readonly CallStack $callStack = new CallStack(),
    ) {
        $this->filter = $hydrator?->propertyFilter();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     */
    public function hydrate(array $data, array $context): object
    {
        if (isset($context[Hydrator::OBJECT_TO_POPULATE])) {
            $object = $context[Hydrator::OBJECT_TO_POPULATE];
            // only unset if it is there, unset() copies the context even if the key does not exist
            unset($context[Hydrator::OBJECT_TO_POPULATE]);

            return $this->hydrateProperties($object, $data, $context, $this->nested($context), true);
        }

        return $this->hydrateProperties($this->metadata->newInstance(), $data, $context, $this->nested($context));
    }

    /**
     * @param T                                        $object
     * @param array<string, mixed>                     $data
     * @param array<string, mixed>                     $context
     * @param array<string, array{class-string, bool}> $nested
     * @param bool                                     $populate the object already exists, it keeps the values of missing fields
     *
     * @return T
     */
    private function hydrateProperties(object $object, array $data, array $context, array $nested, bool $populate = false): object
    {
        $metadata = $this->metadata;
        $constructorParameters = null;

        if ($this->filter !== null) {
            $fields = $this->filter->filter($metadata, $context);

            if ($fields !== null) {
                $data = self::withoutFilteredFields($metadata, $data, $fields);
            }
        }

        foreach ($metadata->properties as $propertyMetadata) {
            if (!array_key_exists($propertyMetadata->fieldName, $data)) {
                $alias = self::alias($propertyMetadata, $data);

                if ($alias === null) {
                    if ($populate || !$propertyMetadata->reflection->isPromoted()) {
                        continue;
                    }

                    $constructorParameters ??= $metadata->promotedConstructorDefaults();

                    if (!array_key_exists($propertyMetadata->propertyName, $constructorParameters)) {
                        continue;
                    }

                    $propertyMetadata->reflection->setValue(
                        $object,
                        $constructorParameters[$propertyMetadata->propertyName]->getDefaultValue(),
                    );

                    continue;
                }

                // the field is stored under a former name
                $data[$propertyMetadata->fieldName] = $data[$alias];
            }

            if ($propertyMetadata->normalizer) {
                try {
                    /** @psalm-suppress MixedAssignment */
                    $value = isset($nested[$propertyMetadata->propertyName])
                        ? $this->hydrateNested(
                            $propertyMetadata->propertyName,
                            $nested[$propertyMetadata->propertyName],
                            $propertyMetadata->normalizer,
                            $data[$propertyMetadata->fieldName],
                            $context,
                        )
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
                $propertyMetadata->reflection->setValue($object, $value);
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
     * Removes the fields of the properties the filter leaves out, also under their aliases, so they are treated as
     * missing. Unknown fields don't matter, they are not read anyway.
     *
     * @param ClassMetadata<object>           $metadata
     * @param array<string, mixed>            $data
     * @param array<string, PropertyMetadata> $fields   the properties to map by field name
     *
     * @return array<string, mixed>
     */
    private static function withoutFilteredFields(ClassMetadata $metadata, array $data, array $fields): array
    {
        foreach ($metadata->properties as $property) {
            if (isset($fields[$property->fieldName])) {
                continue;
            }

            unset($data[$property->fieldName]);

            foreach ($property->aliases as $alias) {
                unset($data[$alias]);
            }
        }

        return $data;
    }

    /**
     * The first alias of the property which is in the data.
     *
     * @param array<string, mixed> $data
     */
    private static function alias(PropertyMetadata $property, array $data): string|null
    {
        foreach ($property->aliases as $alias) {
            if (array_key_exists($alias, $data)) {
                return $alias;
            }
        }

        return null;
    }

    /**
     * @param T                    $object
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extract(object $object, array $context): array
    {
        return $this->extractProperties($object, $context, $this->nested($context));
    }

    /**
     * @param T                                        $object
     * @param array<string, mixed>                     $context
     * @param array<string, array{class-string, bool}> $nested
     *
     * @return array<string, mixed>
     */
    private function extractProperties(object $object, array $context, array $nested): array
    {
        $properties = $this->metadata->properties;

        if ($this->filter !== null) {
            $properties = $this->filter->filter($this->metadata, $context) ?? $properties;
        }

        $objectId = spl_object_id($object);

        if (array_key_exists($objectId, $this->callStack->objects)) {
            $references = array_values($this->callStack->objects);
            $references[] = $object::class;

            throw new CircularReference($references);
        }

        $this->callStack->objects[$objectId] = $object::class;

        try {
            $data = [];

            foreach ($properties as $propertyMetadata) {
                if ($propertyMetadata->normalizer) {
                    try {
                        /** @psalm-suppress MixedAssignment */
                        $data[$propertyMetadata->fieldName] = isset($nested[$propertyMetadata->propertyName])
                            ? $this->extractNested(
                                $propertyMetadata->propertyName,
                                $nested[$propertyMetadata->propertyName],
                                $propertyMetadata->normalizer,
                                $propertyMetadata->reflection->getValue($object),
                                $context,
                            )
                            : $propertyMetadata->normalizer->normalize($propertyMetadata->reflection->getValue($object), $context);
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
                    $data[$propertyMetadata->fieldName] = $propertyMetadata->reflection->getValue($object);
                }
            }
        } finally {
            unset($this->callStack->objects[$objectId]);
        }

        return $data;
    }

    /**
     * The nested objects which are mapped with the mappers of their classes right away, only for calls of the hydrator
     * which created this mapper.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, array{class-string, bool}>
     */
    private function nested(array $context): array
    {
        if (($context[Hydrator::HYDRATOR] ?? null) !== $this->hydrator) {
            return [];
        }

        return $this->nested ??= $this->nestedProperties();
    }

    /**
     * Mapping nested objects right away is only a shortcut, the normalizer leads to the same mapper through the
     * hydrator. Mutations here keep the result and only take the longer way.
     *
     * @return array<string, array{class-string, bool}>
     *
     * @infection-ignore-all
     */
    private function nestedProperties(): array
    {
        $nested = [];

        foreach ($this->metadata->properties as $property) {
            if ($property->normalizer === null) {
                continue;
            }

            $class = self::nestedClass($property->normalizer);

            if ($class === null) {
                continue;
            }

            $nested[$property->propertyName] = $class;
        }

        return $nested;
    }

    /**
     * @return array{class-string, bool}|null [nested class, is a list]
     *
     * @infection-ignore-all see {@see self::nestedProperties()}
     */
    private static function nestedClass(Normalizer $normalizer): array|null
    {
        $list = false;

        if ($normalizer instanceof ArrayNormalizer) {
            $list = true;
            $normalizer = $normalizer->innerNormalizer();
        }

        if (!$normalizer instanceof ObjectNormalizer) {
            return null;
        }

        try {
            $className = $normalizer->className();
        } catch (InvalidType) {
            return null;
        }

        if (!class_exists($className)) {
            return null;
        }

        return [$className, $list];
    }

    /**
     * @param array{class-string, bool} $nested
     * @param array<string, mixed>      $context
     *
     * @infection-ignore-all see {@see self::nestedProperties()}
     */
    private function hydrateNested(string $property, array $nested, Normalizer $normalizer, mixed $value, array $context): mixed
    {
        // null and wrong types are handled by the normalizer
        if (!is_array($value)) {
            return $normalizer->denormalize($value, $context);
        }

        $mapper = $this->nestedMapper($property, $nested[0]);

        // the class normalizer of the nested class is called by the hydrator
        if ($mapper === null) {
            return $normalizer->denormalize($value, $context);
        }

        if ($mapper instanceof self && $mapper->hydrator === $this->hydrator) {
            return $this->hydrateWithReflection($mapper, $nested[1], $normalizer, $value, $context);
        }

        if (!$nested[1]) {
            return $mapper->hydrate($value, $context);
        }

        if (!$normalizer instanceof ArrayNormalizer) {
            return $normalizer->denormalize($value, $context);
        }

        $inner = $normalizer->innerNormalizer();
        $items = [];

        foreach ($value as $key => $item) {
            $items[$key] = is_array($item) ? $mapper->hydrate($item, $context) : $inner->denormalize($item, $context);
        }

        return $items;
    }

    /**
     * @param array{class-string, bool} $nested
     * @param array<string, mixed>      $context
     *
     * @infection-ignore-all see {@see self::nestedProperties()}
     */
    private function extractNested(string $property, array $nested, Normalizer $normalizer, mixed $value, array $context): mixed
    {
        [$className, $list] = $nested;

        if (!$list) {
            // subclasses are extracted with the mapper of their own class by the hydrator
            if (!is_object($value) || $value::class !== $className) {
                return $normalizer->normalize($value, $context);
            }

            $mapper = $this->nestedMapper($property, $className);

            if ($mapper instanceof self && $mapper->hydrator === $this->hydrator) {
                return $mapper->extractProperties($value, $context, $mapper->nested ??= $mapper->nestedProperties());
            }

            // the class normalizer of the nested class is called by the hydrator
            return $mapper === null
                ? $normalizer->normalize($value, $context)
                : $mapper->extract($value, $context);
        }

        if (!is_array($value) || !$normalizer instanceof ArrayNormalizer) {
            return $normalizer->normalize($value, $context);
        }

        $inner = $normalizer->innerNormalizer();
        $items = [];
        $mapper = $this->nestedMapper($property, $className);

        if ($mapper instanceof self && $mapper->hydrator === $this->hydrator) {
            $properties = $mapper->nested ??= $mapper->nestedProperties();

            foreach ($value as $key => $item) {
                $items[$key] = is_object($item) && $item::class === $className
                    ? $mapper->extractProperties($item, $context, $properties)
                    : $inner->normalize($item, $context);
            }

            return $items;
        }

        foreach ($value as $key => $item) {
            if (is_object($item) && $item::class === $className) {
                if ($mapper !== null) {
                    $items[$key] = $mapper->extract($item, $context);

                    continue;
                }
            }

            $items[$key] = $inner->normalize($item, $context);
        }

        return $items;
    }

    /**
     * The nested class is mapped with reflection as well: its properties are hydrated right away, without the checks
     * of hydrate() this mapper already did.
     *
     * @param ReflectionMapper<object> $mapper
     * @param array<string, mixed>     $value
     * @param array<string, mixed>     $context
     *
     * @infection-ignore-all see {@see self::nestedProperties()}
     */
    private function hydrateWithReflection(self $mapper, bool $list, Normalizer $normalizer, array $value, array $context): mixed
    {
        $properties = $mapper->nested ??= $mapper->nestedProperties();

        if (!$list) {
            return $mapper->hydrateProperties($mapper->metadata->newInstance(), $value, $context, $properties);
        }

        if (!$normalizer instanceof ArrayNormalizer) {
            return $normalizer->denormalize($value, $context);
        }

        $inner = $normalizer->innerNormalizer();
        $items = [];

        foreach ($value as $key => $item) {
            $items[$key] = is_array($item)
                ? $mapper->hydrateProperties($mapper->metadata->newInstance(), $item, $context, $properties)
                : $inner->denormalize($item, $context);
        }

        return $items;
    }

    /**
     * @param class-string $className
     *
     * @return Mapper<object>|null
     *
     * @infection-ignore-all see {@see self::nestedProperties()}
     */
    private function nestedMapper(string $property, string $className): Mapper|null
    {
        if (array_key_exists($property, $this->nestedMappers)) {
            return $this->nestedMappers[$property];
        }

        if ($this->hydrator === null) {
            return null;
        }

        return $this->nestedMappers[$property] = $this->hydrator->mapper($className);
    }
}
