<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark;

use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\MetadataHydrator;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileId;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods('setUp')]
final class HydratorBench
{
    private Hydrator $hydrator;

    public function setUp(): void
    {
        $this->hydrator = new MetadataHydrator();

        $object = $this->hydrator->hydrate(ProfileCreated::class, [
            'profileId' => '1',
            'name' => 'foo',
        ]);

        $this->hydrator->extract($object);
    }

    #[Bench\Revs(10)]
    public function benchHydrate1Object(): void
    {
        $this->hydrator->hydrate(ProfileCreated::class, [
            'profileId' => '1',
            'name' => 'foo',
        ]);
    }

    #[Bench\Revs(10)]
    public function benchExtract1Object(): void
    {
        $object = new ProfileCreated(ProfileId::fromString('1'), 'foo');

        $this->hydrator->extract($object);
    }

    #[Bench\Revs(10)]
    public function benchHydrate1_000Objects(): void
    {
        for ($i = 0; $i < 1_000; $i++) {
            $this->hydrator->hydrate(ProfileCreated::class, [
                'profileId' => '1',
                'name' => 'foo',
            ]);
        }
    }

    #[Bench\Revs(10)]
    public function benchExtract1_000Objects(): void
    {
        $object = new ProfileCreated(ProfileId::fromString('1'), 'foo');

        for ($i = 0; $i < 1_000; $i++) {
            $this->hydrator->extract($object);
        }
    }

    #[Bench\Revs(10)]
    public function benchHydrate1_000_000Objects(): void
    {
        for ($i = 0; $i < 1_000_000; $i++) {
            $this->hydrator->hydrate(ProfileCreated::class, [
                'profileId' => '1',
                'name' => 'foo',
            ]);
        }
    }

    #[Bench\Revs(10)]
    public function benchExtract1_000_000Objects(): void
    {
        $object = new ProfileCreated(ProfileId::fromString('1'), 'foo');

        for ($i = 0; $i < 1_000_000; $i++) {
            $this->hydrator->extract($object);
        }
    }
}
