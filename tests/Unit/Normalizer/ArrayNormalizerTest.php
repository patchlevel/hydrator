<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\HydratorAwareNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ArrayNormalizerTest extends TestCase
{
    use ProphecyTrait;

    public function testNormalizeWithNull(): void
    {
        $innerNormalizer = $this->prophesize(Normalizer::class);

        $normalizer = new ArrayNormalizer($innerNormalizer->reveal());
        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $innerNormalizer = $this->prophesize(Normalizer::class);

        $normalizer = new ArrayNormalizer($innerNormalizer->reveal());
        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);

        $innerNormalizer = $this->prophesize(Normalizer::class);

        $normalizer = new ArrayNormalizer($innerNormalizer->reveal());
        $normalizer->normalize('foo');
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);

        $innerNormalizer = $this->prophesize(Normalizer::class);

        $normalizer = new ArrayNormalizer($innerNormalizer->reveal());
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

        $normalizer = new ArrayNormalizer($innerNormalizer);
        $this->assertEquals(['1', '2'], $normalizer->normalize([1, 2]));
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

        $normalizer = new ArrayNormalizer($innerNormalizer);
        $this->assertEquals([1, 2], $normalizer->denormalize(['1', '2']));
    }

    public function testPassHydrator(): void
    {
        $hydrator = $this->prophesize(Hydrator::class)->reveal();
        $normalizer = $this->prophesize(Normalizer::class);
        $normalizer->willImplement(HydratorAwareNormalizer::class);
        $normalizer->setHydrator($hydrator)->shouldBeCalled();

        $normalizer = new ArrayNormalizer($normalizer->reveal());
        $normalizer->setHydrator($hydrator);
    }
}
