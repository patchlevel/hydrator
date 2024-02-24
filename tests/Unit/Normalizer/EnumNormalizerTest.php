<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Tests\Unit\Fixture\AnotherEnum;
use Patchlevel\Hydrator\Tests\Unit\Fixture\AutoTypeDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Status;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use ReflectionClass;
use ReflectionType;
use RuntimeException;

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
        $this->expectExceptionMessage('type "Patchlevel\Hydrator\Tests\Unit\Fixture\Status|null" was expected but "string" was passed.');

        $normalizer = new EnumNormalizer(Status::class);
        $normalizer->normalize('foo');
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('foo');
        $this->expectExceptionMessage('Patchlevel\Hydrator\Tests\Unit\Fixture\Status');

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

    public function testAutoDetect(): void
    {
        $normalizer = new EnumNormalizer();
        $normalizer->setReflectionType($this->reflectionType(AutoTypeDto::class, 'status'));

        self::assertEquals(Status::class, $normalizer->getEnum());
    }

    public function testAutoDetectOverrideNotPossible(): void
    {
        $normalizer = new EnumNormalizer(AnotherEnum::class);
        $normalizer->setReflectionType($this->reflectionType(AutoTypeDto::class, 'status'));

        self::assertEquals(AnotherEnum::class, $normalizer->getEnum());
    }

    public function testAutoDetectMissingType(): void
    {
        $this->expectException(InvalidType::class);

        $normalizer = new EnumNormalizer();
        $normalizer->getEnum();
    }

    /** @param class-string $class */
    private function reflectionType(string $class, string $property): ReflectionType
    {
        $reflection = new ReflectionClass($class);
        $property = $reflection->getProperty($property);

        $type = $property->getType();

        if (!$type instanceof ReflectionType) {
            throw new RuntimeException('no type');
        }

        return $type;
    }
}
