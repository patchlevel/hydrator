<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\MissingHydrator;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ObjectNormalizerTest extends TestCase
{
    use ProphecyTrait;

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
        $hydrator = $this->prophesize(Hydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator->reveal());

        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $hydrator = $this->prophesize(Hydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator->reveal());

        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('type "Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated|null" was expected but "string" was passed.');

        $hydrator = $this->prophesize(Hydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator->reveal());
        $normalizer->normalize('foo');
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('array<string, mixed>|null" was expected but "string" was passed.');

        $hydrator = $this->prophesize(Hydrator::class);

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator->reveal());
        $normalizer->denormalize('foo');
    }

    public function testNormalizeWithValue(): void
    {
        $hydrator = $this->prophesize(Hydrator::class);

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $hydrator->extract($event)
            ->willReturn(['profileId' => '1', 'email' => 'info@patchlevel.de'])
            ->shouldBeCalledOnce();

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator->reveal());

        self::assertEquals(
            $normalizer->normalize($event),
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        );
    }

    public function testDenormalizeWithValue(): void
    {
        $hydrator = $this->prophesize(Hydrator::class);

        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $hydrator->hydrate(ProfileCreated::class, ['profileId' => '1', 'email' => 'info@patchlevel.de'])
            ->willReturn($expected)
            ->shouldBeCalledOnce();

        $normalizer = new ObjectNormalizer(ProfileCreated::class);
        $normalizer->setHydrator($hydrator->reveal());

        $this->assertEquals(
            $expected,
            $normalizer->denormalize(['profileId' => '1', 'email' => 'info@patchlevel.de']),
        );
    }
}
