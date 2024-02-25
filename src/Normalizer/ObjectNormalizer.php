<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Hydrator;
use ReflectionType;

use function is_array;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ObjectNormalizer implements Normalizer, ReflectionTypeAwareNormalizer, HydratorAwareNormalizer
{
    private Hydrator|null $hydrator = null;

    /** @param class-string|null $className */
    public function __construct(
        private string|null $className = null,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function normalize(mixed $value): array|null
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

        return $this->hydrator->extract($value);
    }

    public function denormalize(mixed $value): object|null
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

        return $this->hydrator->hydrate($className, $value);
    }

    public function setHydrator(Hydrator $hydrator): void
    {
        $this->hydrator = $hydrator;
    }

    public function handleReflectionType(ReflectionType|null $reflectionType): void
    {
        if ($this->className !== null || $reflectionType === null) {
            return;
        }

        $this->className = ReflectionTypeUtil::classString($reflectionType);
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
