<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Hydrator;

use function is_array;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ObjectNormalizer implements Normalizer, HydratorAwareNormalizer
{
    public Hydrator|null $hydrator = null;

    public function __construct(
        /** @var class-string */
        public readonly string $className,
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

        if (!$value instanceof $this->className) {
            throw InvalidArgument::withWrongType($this->className . '|null', $value);
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

        return $this->hydrator->hydrate($this->className, $value);
    }

    public function setHydrator(Hydrator $hydrator): void
    {
        $this->hydrator = $hydrator;
    }

    /** @return array{className: class-string, hydrator: null} */
    public function __serialize(): array
    {
        return [
            'className' => $this->className,
            'hydrator' => null,
        ];
    }
}
