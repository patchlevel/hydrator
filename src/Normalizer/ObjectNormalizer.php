<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Hydrator;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\TemplateType;

use function is_array;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final class ObjectNormalizer implements Normalizer, TypeAwareNormalizer
{
    /** @param class-string|null $className */
    public function __construct(
        private string|null $className = null,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function normalize(mixed $value, array $context): mixed
    {
        if ($value === null) {
            return null;
        }

        $className = $this->className();

        if (!$value instanceof $className) {
            throw InvalidArgument::withWrongType($className . '|null', $value);
        }

        return self::hydrator($context)->extract($value, $context);
    }

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $value, array $context): object|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw InvalidArgument::withWrongType('array<string, mixed>|null', $value);
        }

        $className = $this->className();

        return self::hydrator($context)->hydrate($className, $value, $context);
    }

    public function handleType(Type|null $type): void
    {
        if ($type === null || $this->className !== null) {
            return;
        }

        if ($type instanceof NullableType) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof GenericType) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof TemplateType) {
            $type = $type->getWrappedType();
        }

        if (!$type instanceof ObjectType) {
            return;
        }

        $this->className = $type->getClassName();
    }

    /** @return class-string */
    public function className(): string
    {
        if ($this->className === null) {
            throw InvalidType::missingType();
        }

        return $this->className;
    }

    /** @param array<string, mixed> $context */
    private static function hydrator(array $context): Hydrator
    {
        $hydrator = $context[Hydrator::HYDRATOR] ?? null;

        if (!$hydrator instanceof Hydrator) {
            throw new MissingHydrator();
        }

        return $hydrator;
    }
}
