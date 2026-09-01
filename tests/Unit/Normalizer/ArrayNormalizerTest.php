<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use InvalidArgumentException;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\HydratorAwareNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function is_int;

#[CoversClass(ArrayNormalizer::class)]
final class ArrayNormalizerTest extends TestCase
{
    public function testNormalizeWithNull(): void
    {
        $innerNormalizer = $this->createMock(Normalizer::class);

        $normalizer = new ArrayNormalizer($innerNormalizer);
        $this->assertEquals(null, $normalizer->normalize(null, []));
    }

    public function testDenormalizeWithNull(): void
    {
        $innerNormalizer = $this->createMock(Normalizer::class);

        $normalizer = new ArrayNormalizer($innerNormalizer);
        $this->assertEquals(null, $normalizer->denormalize(null, []));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);

        $innerNormalizer = $this->createMock(Normalizer::class);

        $normalizer = new ArrayNormalizer($innerNormalizer);
        $normalizer->normalize('foo', []);
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);

        $innerNormalizer = $this->createMock(Normalizer::class);

        $normalizer = new ArrayNormalizer($innerNormalizer);
        $normalizer->denormalize('foo', []);
    }

    public function testNormalizeWithValue(): void
    {
        $innerNormalizer = new class implements Normalizer {
            /** @param array<string, mixed> $context */
            public function normalize(mixed $value, array $context): string
            {
                return (string)$value;
            }

            /** @param array<string, mixed> $context */
            public function denormalize(mixed $value, array $context): int
            {
                return (int)$value;
            }
        };

        $normalizer = new ArrayNormalizer($innerNormalizer);
        $this->assertEquals(['1', '2'], $normalizer->normalize([1, 2], []));
    }

    public function testDenormalizeWithValue(): void
    {
        $innerNormalizer = new class implements Normalizer {
            /** @param array<string, mixed> $context */
            public function normalize(mixed $value, array $context): string
            {
                return (string)$value;
            }

            /** @param array<string, mixed> $context */
            public function denormalize(mixed $value, array $context): int
            {
                return (int)$value;
            }
        };

        $normalizer = new ArrayNormalizer($innerNormalizer);
        $this->assertEquals([1, 2], $normalizer->denormalize(['1', '2'], []));
    }

    public function testNormalizePassesContextToInnerNormalizer(): void
    {
        $context = ['key' => 'value'];

        $innerNormalizer = new class implements Normalizer {
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

        $normalizer = new ArrayNormalizer($innerNormalizer);
        $normalizer->normalize(['a', 'b'], $context);

        $this->assertSame([$context, $context], $innerNormalizer->contexts);
    }

    public function testDenormalizePassesContextToInnerNormalizer(): void
    {
        $context = ['key' => 'value'];

        $innerNormalizer = new class implements Normalizer {
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

        $normalizer = new ArrayNormalizer($innerNormalizer);
        $normalizer->denormalize(['a', 'b'], $context);

        $this->assertSame([$context, $context], $innerNormalizer->contexts);
    }

    public function testNormalizeDoesNotMutateSourceArrayWithReferencedElement(): void
    {
        $innerNormalizer = new class implements Normalizer {
            /** @param array<string, mixed> $context */
            public function normalize(mixed $value, array $context): int
            {
                if (!is_int($value)) {
                    throw new InvalidArgumentException();
                }

                return $value + 100;
            }

            /** @param array<string, mixed> $context */
            public function denormalize(mixed $value, array $context): int
            {
                if (!is_int($value)) {
                    throw new InvalidArgumentException();
                }

                return $value - 100;
            }
        };

        $source = [1, 2, 3];

        // A leftover reference from a previous `foreach ($source as &$row)` without
        // unset() turns the last element into a PHP reference. Iterating the source
        // by reference inside the normalizer must not write back into it.
        // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedForeach
        foreach ($source as &$row) {
        }

        $normalizer = new ArrayNormalizer($innerNormalizer);
        $result = $normalizer->normalize($source, []);

        self::assertSame([101, 102, 103], $result);
        self::assertSame([1, 2, 3], $source);
    }

    public function testDenormalizeDoesNotMutateSourceArrayWithReferencedElement(): void
    {
        $innerNormalizer = new class implements Normalizer {
            /** @param array<string, mixed> $context */
            public function normalize(mixed $value, array $context): int
            {
                if (!is_int($value)) {
                    throw new InvalidArgumentException();
                }

                return $value + 100;
            }

            /** @param array<string, mixed> $context */
            public function denormalize(mixed $value, array $context): int
            {
                if (!is_int($value)) {
                    throw new InvalidArgumentException();
                }

                return $value - 100;
            }
        };

        $source = [101, 102, 103];

        // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedForeach
        foreach ($source as &$row) {
        }

        $normalizer = new ArrayNormalizer($innerNormalizer);
        $result = $normalizer->denormalize($source, []);

        self::assertSame([1, 2, 3], $result);
        self::assertSame([101, 102, 103], $source);
    }

    public function testPassHydrator(): void
    {
        $hydrator = $this->createMock(Hydrator::class);
        $normalizer = $this->createMockForIntersectionOfInterfaces([Normalizer::class, HydratorAwareNormalizer::class]);
        $normalizer->expects($this->once())->method('setHydrator')->with($hydrator);

        $normalizer = new ArrayNormalizer($normalizer);
        $normalizer->setHydrator($hydrator);
    }

    public function testInnerNormalizer(): void
    {
        $innerNormalizer = $this->createMock(Normalizer::class);
        $normalizer = new ArrayNormalizer($innerNormalizer);

        self::assertSame($innerNormalizer, $normalizer->innerNormalizer());
    }
}
