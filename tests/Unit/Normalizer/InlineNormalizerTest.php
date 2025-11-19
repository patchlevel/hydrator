<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use Patchlevel\Hydrator\Normalizer\InlineNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use PHPUnit\Framework\TestCase;

final class InlineNormalizerTest extends TestCase
{
    public function testNormalizeWithValue(): void
    {
        $normalizer = new InlineNormalizer(
            static fn (int $value): string => (string)$value,
            static fn (string $value): int => (int)$value,
        );

        $this->assertEquals('123', $normalizer->normalize(123));
    }

    public function testDenormalizeWithValue(): void
    {
        $normalizer = new InlineNormalizer(
            static fn (int $value): string => (string)$value,
            static fn (string $value): int => (int)$value,
        );

        $this->assertEquals(123, $normalizer->denormalize('123'));
    }

    public function testNormalizeWithNull(): void
    {
        $normalizer = new InlineNormalizer(
            static fn (mixed $value) => 'not null',
            static fn (mixed $value) => 'not null',
        );

        $this->assertNull($normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $normalizer = new InlineNormalizer(
            static fn (mixed $value) => 'not null',
            static fn (mixed $value) => 'not null',
        );

        $this->assertNull($normalizer->denormalize(null));
    }

    public function testNormalizePassNull(): void
    {
        $normalizer = new InlineNormalizer(
            static fn (mixed $value) => $value === null ? 'is null' : 'is not null',
            static fn (mixed $value) => $value,
            true,
        );

        $this->assertEquals('is null', $normalizer->normalize(null));
    }

    public function testDenormalizePassNull(): void
    {
        $normalizer = new InlineNormalizer(
            static fn (mixed $value) => $value,
            static fn (mixed $value) => $value === null ? 'is null' : 'is not null',
            true,
        );

        $this->assertEquals('is null', $normalizer->denormalize(null));
    }

    public function testNormalizeInvalidType(): void
    {
        $this->expectException(InvalidType::class);

        $normalizer = new InlineNormalizer(
            static fn (string $value) => $value,
            static fn (mixed $value) => $value,
        );

        $normalizer->normalize(123);
    }

    public function testDenormalizeInvalidType(): void
    {
        $this->expectException(InvalidType::class);

        $normalizer = new InlineNormalizer(
            static fn (mixed $value) => $value,
            static fn (string $value) => $value,
        );

        $normalizer->denormalize(123);
    }
}
