<?php

declare(strict_types=1);

namespace Unit;

use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\LazyHydrator;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;

#[RequiresPhp('>= 8.4')]
class LazyHydratorTest extends TestCase
{
    public function testNoHydration(): void
    {
        $realHydrator = $this->createMock(Hydrator::class);
        $realHydrator->expects($this->never())->method('hydrate')->withAnyParameters();

        $hydrator = new LazyHydrator($realHydrator);

        $object = $hydrator->hydrate(
            ProfileCreated::class,
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        );

        self::assertInstanceOf(ProfileCreated::class, $object);
    }

    public function testTriggerHydration(): void
    {
        $realHydrator = $this->createMock(Hydrator::class);
        $realHydrator->expects($this->once())->method('hydrate')->with(
            ProfileCreated::class,
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        )->willReturn(new ProfileCreated(
            profileId: ProfileId::fromString('1'),
            email: Email::fromString('info@patchlevel.de'),
        ));

        $hydrator = new LazyHydrator($realHydrator);

        $object = $hydrator->hydrate(
            ProfileCreated::class,
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        );

        self::assertInstanceOf(ProfileCreated::class, $object);
        self::assertSame('1', $object->profileId->toString());
    }
}
