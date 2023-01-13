<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Status;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class EnumNormalizerTest extends TestCase
{
    use ProphecyTrait;

    public function testNormalizeWithNull(): void
    {
        $normalizer = new EnumNormalizer(Status::class);
        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $normalizer = new EnumNormalizer(Status::class);
        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);

        $normalizer = new EnumNormalizer(Status::class);
        $normalizer->normalize('foo');
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);

        $normalizer = new EnumNormalizer(Status::class);
        $normalizer->denormalize('foo');
    }

    public function testNormalizeWithValue(): void
    {
        $normalizer = new EnumNormalizer(Status::class);
        $this->assertEquals('pending', $normalizer->normalize(Status::Pending));
    }

    public function testDenormalizeWithValue(): void
    {
        $normalizer = new EnumNormalizer(Status::class);
        $this->assertEquals(Status::Pending, $normalizer->denormalize('pending'));
    }
}
