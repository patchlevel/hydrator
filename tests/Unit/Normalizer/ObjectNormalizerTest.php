<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Normalizer\MissingHydrator;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\AutoTypeDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

use function serialize;
use function unserialize;

#[CoversClass(ObjectNormalizer::class)]
final class ObjectNormalizerTest extends TestCase
{
    public function testNormalizeMissingHydrator(): void
    {
        $this->expectException(MissingHydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->normalize(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')), ['hydrator' => 'not a hydrator']);
    }

    public function testDenormalizeMissingHydrator(): void
    {
        $this->expectException(MissingHydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->denormalize(['profileId' => '1', 'email' => 'info@patchlevel.de'], []);
    }

    public function testNullDoesNotNeedHydrator(): void
    {
        $normalizer = new ObjectNormalizer(ProfileCreated::class);

        self::assertNull($normalizer->normalize(null, []));
        self::assertNull($normalizer->denormalize(null, []));
    }

    public function testNormalizeWithNull(): void
    {
        $hydrator = $this->createMock(Hydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);

        $this->assertEquals(null, $normalizer->normalize(null, [Hydrator::HYDRATOR => $hydrator]));
    }

    public function testDenormalizeWithNull(): void
    {
        $hydrator = $this->createMock(Hydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);

        $this->assertEquals(null, $normalizer->denormalize(null, [Hydrator::HYDRATOR => $hydrator]));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('type "Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated|null" was expected but "string" was passed.');

        $hydrator = $this->createMock(Hydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->normalize('foo', [Hydrator::HYDRATOR => $hydrator]);
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

        self::assertEquals(
            $normalizer->normalize($event, [Hydrator::HYDRATOR => $hydrator]),
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

        $this->assertEquals(
            $expected,
            $normalizer->denormalize(['profileId' => '1', 'email' => 'info@patchlevel.de'], [Hydrator::HYDRATOR => $hydrator]),
        );
    }

    public function testNormalizePassesContextToHydrator(): void
    {
        $hydrator = $this->createMock(Hydrator::class);
        $context = ['key' => 'value', Hydrator::HYDRATOR => $hydrator];

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $hydrator->expects($this->once())->method('extract')->with($event, $context)
            ->willReturn(['profileId' => '1', 'email' => 'info@patchlevel.de']);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);

        self::assertEquals(
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
            $normalizer->normalize($event, $context),
        );
    }

    public function testDenormalizePassesContextToHydrator(): void
    {
        $hydrator = $this->createMock(Hydrator::class);
        $context = ['key' => 'value', Hydrator::HYDRATOR => $hydrator];

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

        $this->assertEquals(
            $expected,
            $normalizer->denormalize(['profileId' => '1', 'email' => 'info@patchlevel.de'], $context),
        );
    }

    public function testAutoDetect(): void
    {
        $normalizer = new ObjectNormalizer();
        $normalizer->handleType(Type::object(ProfileCreated::class));

        self::assertEquals(ProfileCreated::class, $normalizer->className());
    }

    public function testAutoDetectOverrideNotPossible(): void
    {
        $normalizer = new ObjectNormalizer(AutoTypeDto::class);
        $normalizer->handleType(Type::object(ProfileCreated::class));

        self::assertEquals(AutoTypeDto::class, $normalizer->className());
    }

    public function testAutoDetectMissingType(): void
    {
        $this->expectException(InvalidType::class);

        $normalizer = new ObjectNormalizer();

        $normalizer->className();
    }

    public function testAutoDetectMissingTypeBecauseNull(): void
    {
        $this->expectException(InvalidType::class);

        $normalizer = new ObjectNormalizer();
        $normalizer->handleType(null);

        $normalizer->className();
    }

    public function testGeneric(): void
    {
        $normalizer = new ObjectNormalizer();
        $normalizer->handleType(Type::generic(Type::object(ProfileCreated::class)));

        self::assertEquals(ProfileCreated::class, $normalizer->className());
    }

    public function testTemplate(): void
    {
        $normalizer = new ObjectNormalizer();
        $normalizer->handleType(Type::template('T', Type::object(ProfileCreated::class)));

        self::assertEquals(ProfileCreated::class, $normalizer->className());
    }

    public function testSerialize(): void
    {
        $normalizer = new ObjectNormalizer(ProfileCreated::class);

        $serialized = serialize($normalizer);

        $normalizer2 = unserialize($serialized);

        self::assertInstanceOf(ObjectNormalizer::class, $normalizer2);
        self::assertEquals(new ObjectNormalizer(ProfileCreated::class), $normalizer2);
    }
}
