<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\MissingHydrator;
use Patchlevel\Hydrator\Normalizer\ObjectMapNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function serialize;
use function unserialize;

#[CoversClass(ObjectMapNormalizer::class)]
final class ObjectMapNormalizerTest extends TestCase
{
    public function testNormalizeMissingHydrator(): void
    {
        $this->expectException(MissingHydrator::class);

        $normalizer = new ObjectMapNormalizer([ProfileCreated::class => 'created']);
        $this->assertEquals(null, $normalizer->normalize(null, []));
    }

    public function testDenormalizeMissingHydrator(): void
    {
        $this->expectException(MissingHydrator::class);

        $normalizer = new ObjectMapNormalizer([ProfileCreated::class => 'created']);
        $this->assertEquals(null, $normalizer->denormalize(null, []));
    }

    public function testNormalizeWithNull(): void
    {
        $hydrator = $this->createStub(Hydrator::class);

        $normalizer = new ObjectMapNormalizer([ProfileCreated::class => 'created']);
        $normalizer->setHydrator($hydrator);

        $this->assertEquals(null, $normalizer->normalize(null, []));
    }

    public function testDenormalizeWithNull(): void
    {
        $hydrator = $this->createStub(Hydrator::class);

        $normalizer = new ObjectMapNormalizer([ProfileCreated::class => 'created']);
        $normalizer->setHydrator($hydrator);

        $this->assertEquals(null, $normalizer->denormalize(null, []));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('type "Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated|null" was expected but "string" was passed.');

        $hydrator = $this->createStub(Hydrator::class);

        $normalizer = new ObjectMapNormalizer([ProfileCreated::class => 'created']);
        $normalizer->setHydrator($hydrator);
        $normalizer->normalize('foo', []);
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('array<string, mixed>|null" was expected but "string" was passed.');

        $hydrator = $this->createStub(Hydrator::class);

        $normalizer = new ObjectMapNormalizer([ProfileCreated::class => 'created']);
        $normalizer->setHydrator($hydrator);
        $normalizer->denormalize('foo', []);
    }

    public function testNormalizeWithValue(): void
    {
        $hydrator = $this->createMock(Hydrator::class);

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $hydrator
            ->expects($this->once())
            ->method('extract')
            ->with($event)
            ->willReturn(['profileId' => '1', 'email' => 'info@patchlevel.de']);

        $normalizer = new ObjectMapNormalizer([ProfileCreated::class => 'created']);
        $normalizer->setHydrator($hydrator);

        self::assertEquals(
            $normalizer->normalize($event, []),
            ['profileId' => '1', 'email' => 'info@patchlevel.de', '_type' => 'created'],
        );
    }

    public function testDenormalizeWithValue(): void
    {
        $hydrator = $this->createMock(Hydrator::class);

        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $hydrator
            ->expects($this->once())
            ->method('hydrate')
            ->with(ProfileCreated::class, ['profileId' => '1', 'email' => 'info@patchlevel.de'])
            ->willReturn($expected);

        $normalizer = new ObjectMapNormalizer([ProfileCreated::class => 'created']);
        $normalizer->setHydrator($hydrator);

        $this->assertEquals(
            $expected,
            $normalizer->denormalize(['profileId' => '1', 'email' => 'info@patchlevel.de', '_type' => 'created'], []),
        );
    }

    public function testNormalizePassesContextToHydrator(): void
    {
        $context = ['key' => 'value'];
        $hydrator = $this->createMock(Hydrator::class);

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $hydrator
            ->expects($this->once())
            ->method('extract')
            ->with($event, $context)
            ->willReturn(['profileId' => '1', 'email' => 'info@patchlevel.de']);

        $normalizer = new ObjectMapNormalizer([ProfileCreated::class => 'created']);
        $normalizer->setHydrator($hydrator);

        self::assertEquals(
            ['profileId' => '1', 'email' => 'info@patchlevel.de', '_type' => 'created'],
            $normalizer->normalize($event, $context),
        );
    }

    public function testDenormalizePassesContextToHydrator(): void
    {
        $context = ['key' => 'value'];
        $hydrator = $this->createMock(Hydrator::class);

        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $hydrator
            ->expects($this->once())
            ->method('hydrate')
            ->with(ProfileCreated::class, ['profileId' => '1', 'email' => 'info@patchlevel.de'], $context)
            ->willReturn($expected);

        $normalizer = new ObjectMapNormalizer([ProfileCreated::class => 'created']);
        $normalizer->setHydrator($hydrator);

        $this->assertEquals(
            $expected,
            $normalizer->denormalize(['profileId' => '1', 'email' => 'info@patchlevel.de', '_type' => 'created'], $context),
        );
    }

    public function testSerialize(): void
    {
        $hydrator = $this->createStub(Hydrator::class);

        $normalizer = new ObjectMapNormalizer([ProfileCreated::class => 'created']);
        $normalizer->setHydrator($hydrator);

        $serialized = serialize($normalizer);
        $normalizer2 = unserialize($serialized);

        self::assertInstanceOf(ObjectMapNormalizer::class, $normalizer2);
        self::assertEquals(new ObjectMapNormalizer([ProfileCreated::class => 'created']), $normalizer2);
    }
}
