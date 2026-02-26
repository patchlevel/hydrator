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

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final class ObjectNormalizer implements Normalizer, TypeAwareNormalizer, HydratorAwareNormalizer
{
    private Hydrator|null $hydrator = null;

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

        if (!$this->hydrator) {
            throw new MissingHydrator();
        }

        $className = $this->getClassName();

        if (!$value instanceof $className) {
            throw InvalidArgument::withWrongType($className . '|null', $value);
        }

        return $this->hydrator->extract($value, $context);
    }

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $value, array $context): object|null
    {
        if ($value === null) {
            return null;
        }

        if (!$this->hydrator) {
            throw new MissingHydrator();
        }

        $className = $this->getClassName();

        return $this->hydrator->hydrate($className, $value, $context);
    }

    public function setHydrator(Hydrator $hydrator): void
    {
        $this->hydrator = $hydrator;
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
    public function getClassName(): string
    {
        if ($this->className === null) {
            throw InvalidType::missingType();
        }

        return $this->className;
    }

    /** @return array{className: class-string|null, hydrator: null} */
    public function __serialize(): array
    {
        return [
            'className' => $this->className,
            'hydrator' => null,
        ];
    }
}
