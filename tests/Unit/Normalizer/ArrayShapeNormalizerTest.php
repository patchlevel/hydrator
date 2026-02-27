<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Normalizer\ArrayShapeNormalizer;
use Patchlevel\Hydrator\Normalizer\HydratorAwareNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\NormalizerWithContext;
use PHPUnit\Framework\TestCase;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ArrayShapeNormalizerTest extends TestCase
{
    public function testNormalizeWithNull(): void
    {
        $innerNormalizer = $this->createMock(Normalizer::class);

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);
        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $innerNormalizer = $this->createMock(Normalizer::class);

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);
        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);

        $innerNormalizer = $this->createMock(Normalizer::class);

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);
        $normalizer->normalize('foo');
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);

        $innerNormalizer = $this->createMock(Normalizer::class);

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);
        $normalizer->denormalize('foo');
    }

    public function testNormalizeWithValue(): void
    {
        $innerNormalizer = new class implements Normalizer {
            public function normalize(mixed $value): string
            {
                return (string)$value;
            }

            public function denormalize(mixed $value): int
            {
                return (int)$value;
            }
        };

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);
        $this->assertEquals(['foo' => '1'], $normalizer->normalize(['foo' => 1]));
    }

    public function testDenormalizeWithValue(): void
    {
        $innerNormalizer = new class implements Normalizer {
            public function normalize(mixed $value): string
            {
                return (string)$value;
            }

            public function denormalize(mixed $value): int
            {
                return (int)$value;
            }
        };

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);
        $this->assertEquals(['foo' => 1], $normalizer->denormalize(['foo' => '1']));
    }

    public function testNormalizePassesContextToInnerNormalizer(): void
    {
        $context = ['key' => 'value'];

        $innerNormalizer = new class implements NormalizerWithContext {
            /** @var array<int, array<string, mixed>> */
            public array $contexts = [];

            /** @param array<string, mixed> $context */
            public function normalize(mixed $value, array $context = []): mixed
            {
                $this->contexts[] = $context;

                return $value;
            }

            /** @param array<string, mixed> $context */
            public function denormalize(mixed $value, array $context = []): mixed
            {
                $this->contexts[] = $context;

                return $value;
            }
        };

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer, 'bar' => $innerNormalizer]);
        $normalizer->normalize(['foo' => 'a', 'bar' => 'b'], $context);

        $this->assertSame([$context, $context], $innerNormalizer->contexts);
    }

    public function testDenormalizePassesContextToInnerNormalizer(): void
    {
        $context = ['key' => 'value'];

        $innerNormalizer = new class implements NormalizerWithContext {
            /** @var array<int, array<string, mixed>> */
            public array $contexts = [];

            /** @param array<string, mixed> $context */
            public function normalize(mixed $value, array $context = []): mixed
            {
                $this->contexts[] = $context;

                return $value;
            }

            /** @param array<string, mixed> $context */
            public function denormalize(mixed $value, array $context = []): mixed
            {
                $this->contexts[] = $context;

                return $value;
            }
        };

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer, 'bar' => $innerNormalizer]);
        $normalizer->denormalize(['foo' => 'a', 'bar' => 'b'], $context);

        $this->assertSame([$context, $context], $innerNormalizer->contexts);
    }

    public function testPassHydrator(): void
    {
        $hydrator = $this->createMock(Hydrator::class);
        $normalizer = $this->createMockForIntersectionOfInterfaces([Normalizer::class, HydratorAwareNormalizer::class]);
        $normalizer->expects($this->once())->method('setHydrator')->with($hydrator);

        $normalizer = new ArrayShapeNormalizer(['foo' => $normalizer]);
        $normalizer->setHydrator($hydrator);
    }
}
