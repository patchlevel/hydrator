<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Normalizer\InlineNormalizer;

#[InlineNormalizer(
    normalize: static function (self $object): string {
        return $object->toString();
    },
    denormalize: static function (string $value): self {
        return self::fromString($value);
    },
)]
final class ValueObject
{
    private function __construct(
        private readonly string $value,
    ) {
    }

    public function toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
