<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Normalizer\ArrayShapeNormalizer;
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;
use Patchlevel\Hydrator\Normalizer\HydratorAwareNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\TypeAwareNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

#[CoversClass(ArrayShapeNormalizer::class)]
final class ArrayShapeNormalizerTest extends TestCase
{
    public function testNormalizeWithNull(): void
    {
        $innerNormalizer = $this->createMock(Normalizer::class);

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);
        $this->assertEquals(null, $normalizer->normalize(null, []));
    }

    public function testDenormalizeWithNull(): void
    {
        $innerNormalizer = $this->createMock(Normalizer::class);

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);
        $this->assertEquals(null, $normalizer->denormalize(null, []));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);

        $innerNormalizer = $this->createMock(Normalizer::class);

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);
        $normalizer->normalize('foo', []);
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);

        $innerNormalizer = $this->createMock(Normalizer::class);

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);
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

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);
        $this->assertEquals(['foo' => '1'], $normalizer->normalize(['foo' => 1], []));
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

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);
        $this->assertEquals(['foo' => 1], $normalizer->denormalize(['foo' => '1'], []));
    }

    public function testNormalizeKeepsKeysWithoutNormalizer(): void
    {
        $innerNormalizer = $this->createMock(Normalizer::class);
        $innerNormalizer->expects($this->once())->method('normalize')->with(1, [])->willReturn('1');

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);

        $this->assertSame(
            ['height' => 180, 'foo' => '1', 'note' => null, 'unknown' => ['x']],
            $normalizer->normalize(['height' => 180, 'foo' => 1, 'note' => null, 'unknown' => ['x']], []),
        );
    }

    public function testDenormalizeKeepsKeysWithoutNormalizer(): void
    {
        $innerNormalizer = $this->createMock(Normalizer::class);
        $innerNormalizer->expects($this->once())->method('denormalize')->with('1', [])->willReturn(1);

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);

        $this->assertSame(
            ['height' => 180, 'foo' => 1, 'note' => null, 'unknown' => ['x']],
            $normalizer->denormalize(['height' => 180, 'foo' => '1', 'note' => null, 'unknown' => ['x']], []),
        );
    }

    public function testNormalizePassesNullToInnerNormalizer(): void
    {
        $innerNormalizer = $this->createMock(Normalizer::class);
        $innerNormalizer->expects($this->once())->method('normalize')->with(null, [])->willReturn(null);

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);

        $this->assertSame(['foo' => null], $normalizer->normalize(['foo' => null], []));
    }

    public function testDenormalizePassesNullToInnerNormalizer(): void
    {
        $innerNormalizer = $this->createMock(Normalizer::class);
        $innerNormalizer->expects($this->once())->method('denormalize')->with(null, [])->willReturn(null);

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);

        $this->assertSame(['foo' => null], $normalizer->denormalize(['foo' => null], []));
    }

    public function testNormalizeSkipsMissingKeys(): void
    {
        $innerNormalizer = $this->createMock(Normalizer::class);
        $innerNormalizer->expects($this->never())->method('normalize');

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);

        $this->assertSame(['bar' => 1], $normalizer->normalize(['bar' => 1], []));
    }

    public function testDenormalizeSkipsMissingKeys(): void
    {
        $innerNormalizer = $this->createMock(Normalizer::class);
        $innerNormalizer->expects($this->never())->method('denormalize');

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);

        $this->assertSame(['bar' => 1], $normalizer->denormalize(['bar' => 1], []));
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

        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer, 'bar' => $innerNormalizer]);
        $normalizer->normalize(['foo' => 'a', 'bar' => 'b'], $context);

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

    public function testHandleTypePassesFieldTypes(): void
    {
        $innerNormalizer = new EnumNormalizer();

        $normalizer = new ArrayShapeNormalizer(['status' => $innerNormalizer]);
        $normalizer->handleType(Type::nullable(Type::arrayShape(['status' => Type::enum(Status::class)])));

        self::assertSame(Status::class, $innerNormalizer->className());
    }

    public function testHandleTypeSkipsFieldsMissingInShape(): void
    {
        $statusNormalizer = new EnumNormalizer();

        $missingNormalizer = $this->createMockForIntersectionOfInterfaces([Normalizer::class, TypeAwareNormalizer::class]);
        $missingNormalizer->expects($this->never())->method('handleType');

        $normalizer = new ArrayShapeNormalizer(['status' => $statusNormalizer, 'missing' => $missingNormalizer]);
        $normalizer->handleType(Type::arrayShape(['status' => Type::enum(Status::class)]));

        self::assertSame(Status::class, $statusNormalizer->className());
    }

    public function testInnerNormalizers(): void
    {
        $innerNormalizer = $this->createMock(Normalizer::class);
        $normalizer = new ArrayShapeNormalizer(['foo' => $innerNormalizer]);

        self::assertSame(['foo' => $innerNormalizer], $normalizer->innerNormalizers());
    }
}
