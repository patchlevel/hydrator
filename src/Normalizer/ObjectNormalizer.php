<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\HydratorWithContext;
use ReflectionType;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\TemplateType;

use function is_array;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final class ObjectNormalizer implements NormalizerWithContext, ReflectionTypeAwareNormalizer, TypeAwareNormalizer, HydratorAwareNormalizer
{
    private Hydrator|null $hydrator = null;

    /** @param class-string|null $className */
    public function __construct(
        private string|null $className = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>|null
     */
    public function normalize(mixed $value, array $context = []): array|null
    {
        if (!$this->hydrator) {
            throw new MissingHydrator();
        }

        if ($value === null) {
            return null;
        }

        $className = $this->getClassName();

        if (!$value instanceof $className) {
            throw InvalidArgument::withWrongType($className . '|null', $value);
        }

        if ($this->hydrator instanceof HydratorWithContext) {
            return $this->hydrator->extract($value, $context);
        }

        return $this->hydrator->extract($value);
    }

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $value, array $context = []): object|null
    {
        if (!$this->hydrator) {
            throw new MissingHydrator();
        }

        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw InvalidArgument::withWrongType('array<string, mixed>|null', $value);
        }

        $className = $this->getClassName();

        if ($this->hydrator instanceof HydratorWithContext) {
            return $this->hydrator->hydrate($className, $value, $context);
        }

        return $this->hydrator->hydrate($className, $value);
    }

    public function setHydrator(Hydrator $hydrator): void
    {
        $this->hydrator = $hydrator;
    }

    /** @deprecated use handleType instead */
    public function handleReflectionType(ReflectionType|null $reflectionType): void
    {
        if ($this->className !== null || $reflectionType === null) {
            return;
        }

        $this->className = ReflectionTypeUtil::classString($reflectionType);
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
