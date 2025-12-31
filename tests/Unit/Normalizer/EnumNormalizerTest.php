<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use Patchlevel\Hydrator\Normalizer\EnumNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Tests\Unit\Fixture\AnotherEnum;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

#[CoversClass(EnumNormalizer::class)]
final class EnumNormalizerTest extends TestCase
{
    public function testNormalizeWithNull(): void
    {
        $normalizer = new EnumNormalizer(Status::class);
        $this->assertEquals(null, $normalizer->normalize(null, []));
    }

    public function testDenormalizeWithNull(): void
    {
        $normalizer = new EnumNormalizer(Status::class);
        $this->assertEquals(null, $normalizer->denormalize(null, []));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('type "Patchlevel\Hydrator\Tests\Unit\Fixture\Status|null" was expected but "string" was passed.');

        $normalizer = new EnumNormalizer(Status::class);
        $normalizer->normalize('foo', []);
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('foo');
        $this->expectExceptionMessage('Patchlevel\Hydrator\Tests\Unit\Fixture\Status');

        $normalizer = new EnumNormalizer(Status::class);
        $normalizer->denormalize('foo', []);
    }

    public function testNormalizeWithValue(): void
    {
        $normalizer = new EnumNormalizer(Status::class);
        $this->assertEquals('pending', $normalizer->normalize(Status::Pending, []));
    }

    public function testDenormalizeWithValue(): void
    {
        $normalizer = new EnumNormalizer(Status::class);
        $this->assertEquals(Status::Pending, $normalizer->denormalize('pending', []));
    }

    public function testAutoDetect(): void
    {
        $normalizer = new EnumNormalizer();
        $normalizer->handleType(Type::enum(Status::class));

        self::assertEquals(Status::class, $normalizer->getEnum());
    }

    public function testAutoDetectOverrideNotPossible(): void
    {
        $normalizer = new EnumNormalizer(AnotherEnum::class);
        $normalizer->handleType(Type::enum(Status::class));

        self::assertEquals(AnotherEnum::class, $normalizer->getEnum());
    }

    public function testAutoDetectMissingType(): void
    {
        $this->expectException(InvalidType::class);

        $normalizer = new EnumNormalizer();
        $normalizer->getEnum();
    }

    public function testAutoDetectMissingTypeBecauseNull(): void
    {
        $this->expectException(InvalidType::class);

        $normalizer = new EnumNormalizer();
        $normalizer->handleType(null);

        $normalizer->getEnum();
    }
}
