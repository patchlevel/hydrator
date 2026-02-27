<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\HydratorWithContext;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Normalizer\MissingHydrator;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\AutoTypeDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionType;
use RuntimeException;
use Symfony\Component\TypeInfo\Type;

use function serialize;
use function unserialize;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ObjectNormalizerTest extends TestCase
{
    public function testNormalizeMissingHydrator(): void
    {
        $this->expectException(MissingHydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeMissingHydrator(): void
    {
        $this->expectException(MissingHydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithNull(): void
    {
        $hydrator = $this->createMock(Hydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator);

        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $hydrator = $this->createMock(Hydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator);

        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('type "Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated|null" was expected but "string" was passed.');

        $hydrator = $this->createMock(Hydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator);
        $normalizer->normalize('foo');
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('array<string, mixed>|null" was expected but "string" was passed.');

        $hydrator = $this->createMock(Hydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator);
        $normalizer->denormalize('foo');
    }

    public function testNormalizeWithValue(): void
    {
        $hydrator = $this->createMock(Hydrator::class);

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $hydrator->expects($this->once())->method('extract')->with($event)
            ->willReturn(['profileId' => '1', 'email' => 'info@patchlevel.de']);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator);

        self::assertEquals(
            $normalizer->normalize($event),
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        );
    }

    public function testDenormalizeWithValue(): void
    {
        $hydrator = $this->createMock(Hydrator::class);

        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $hydrator->expects($this->once())->method('hydrate')->with(
            ProfileCreated::class,
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        )
            ->willReturn($expected);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator);

        $this->assertEquals(
            $expected,
            $normalizer->denormalize(['profileId' => '1', 'email' => 'info@patchlevel.de']),
        );
    }

    public function testNormalizePassesContextToHydrator(): void
    {
        $context = ['key' => 'value'];
        $hydrator = $this->createMock(HydratorWithContext::class);

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $hydrator->expects($this->once())->method('extract')->with($event, $context)
            ->willReturn(['profileId' => '1', 'email' => 'info@patchlevel.de']);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator);

        self::assertEquals(
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
            $normalizer->normalize($event, $context),
        );
    }

    public function testDenormalizePassesContextToHydrator(): void
    {
        $context = ['key' => 'value'];
        $hydrator = $this->createMock(HydratorWithContext::class);

        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $hydrator->expects($this->once())->method('hydrate')->with(
            ProfileCreated::class,
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
            $context,
        )->willReturn($expected);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator);

        $this->assertEquals(
            $expected,
            $normalizer->denormalize(['profileId' => '1', 'email' => 'info@patchlevel.de'], $context),
        );
    }

    public function testAutoDetect(): void
    {
        $hydrator = $this->createMock(Hydrator::class);

        $normalizer = new ObjectNormalizer();
        $normalizer->setHydrator($hydrator);
        $normalizer->handleReflectionType($this->reflectionType(AutoTypeDto::class, 'profileCreated'));

        self::assertEquals(ProfileCreated::class, $normalizer->getClassName());
    }

    public function testAutoDetectOverrideNotPossible(): void
    {
        $hydrator = $this->createMock(Hydrator::class);

        $normalizer = new ObjectNormalizer(AutoTypeDto::class);
        $normalizer->setHydrator($hydrator);
        $normalizer->handleReflectionType($this->reflectionType(AutoTypeDto::class, 'profileCreated'));

        self::assertEquals(AutoTypeDto::class, $normalizer->getClassName());
    }

    public function testAutoDetectMissingType(): void
    {
        $this->expectException(InvalidType::class);

        $hydrator = $this->createMock(Hydrator::class);

        $normalizer = new ObjectNormalizer();
        $normalizer->setHydrator($hydrator);

        $normalizer->getClassName();
    }

    public function testAutoDetectMissingTypeBecauseNull(): void
    {
        $this->expectException(InvalidType::class);

        $hydrator = $this->createMock(Hydrator::class);

        $normalizer = new ObjectNormalizer();
        $normalizer->setHydrator($hydrator);
        $normalizer->handleReflectionType(null);

        $normalizer->getClassName();
    }

    public function testGeneric(): void
    {
        $hydrator = $this->createMock(Hydrator::class);

        $normalizer = new ObjectNormalizer();
        $normalizer->setHydrator($hydrator);
        $normalizer->handleType(Type::generic(Type::object(ProfileCreated::class)));

        self::assertEquals(ProfileCreated::class, $normalizer->getClassName());
    }

    public function testTemplate(): void
    {
        $hydrator = $this->createMock(Hydrator::class);

        $normalizer = new ObjectNormalizer();
        $normalizer->setHydrator($hydrator);
        $normalizer->handleType(Type::template('T', Type::object(ProfileCreated::class)));

        self::assertEquals(ProfileCreated::class, $normalizer->getClassName());
    }

    public function testSerialize(): void
    {
        $hydrator = $this->createMock(Hydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator);

        $serialized = serialize($normalizer);

        $normalizer2 = unserialize($serialized);

        self::assertInstanceOf(ObjectNormalizer::class, $normalizer2);
        self::assertEquals(new ObjectNormalizer(ProfileCreated::class), $normalizer2);
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
