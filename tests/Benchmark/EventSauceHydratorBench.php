<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark;

use EventSauce\ObjectHydrator\ObjectMapper;
use EventSauce\ObjectHydrator\ObjectMapperUsingReflection;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\Skill;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods('setUp')]
final class EventSauceHydratorBench
{
    private ObjectMapper $hydrator;

    public function __construct()
    {
        $this->hydrator = new ObjectMapperUsingReflection();
    }

    public function setUp(): void
    {
        $object = $this->hydrator->hydrateObject(
            ProfileCreated::class,
            [
                'profile_id' => '1',
                'name' => 'foo',
                'skills' => [
                    ['name' => 'php'],
                    ['name' => 'symfony'],
                ],
            ],
        );

        $this->hydrator->serializeObject($object);
    }

    #[Bench\Revs(5)]
    public function benchHydrate1Object(): void
    {
        $this->hydrator->hydrateObject(ProfileCreated::class, [
            'profile_id' => '1',
            'name' => 'foo',
            'skills' => [
                ['name' => 'php'],
                ['name' => 'symfony'],
            ],
        ]);
    }

    #[Bench\Revs(5)]
    public function benchExtract1Object(): void
    {
        $object = new ProfileCreated(
            ProfileId::fromString('1'),
            'foo',
            [
                new Skill('php'),
                new Skill('symfony'),
            ],
        );

        $this->hydrator->serializeObject($object);
    }

    #[Bench\Revs(3)]
    public function benchHydrate1000Objects(): void
    {
        for ($i = 0; $i < 1_000; $i++) {
            $this->hydrator->hydrateObject(ProfileCreated::class, [
                'profile_id' => '1',
                'name' => 'foo',
                'skills' => [
                    ['name' => 'php'],
                    ['name' => 'symfony'],
                ],
            ]);
        }
    }

    #[Bench\Revs(3)]
    public function benchExtract1000Objects(): void
    {
        $object = new ProfileCreated(
            ProfileId::fromString('1'),
            'foo',
            [
                new Skill('php'),
                new Skill('symfony'),
            ],
        );

        for ($i = 0; $i < 1_000; $i++) {
            $this->hydrator->serializeObject($object);
        }
    }

    #[Bench\Revs(3)]
    public function benchHydrate1000000Objects(): void
    {
        for ($i = 0; $i < 1_000_000; $i++) {
            $this->hydrator->hydrateObject(ProfileCreated::class, [
                'profile_id' => '1',
                'name' => 'foo',
                'skills' => [
                    ['name' => 'php'],
                    ['name' => 'symfony'],
                ],
            ]);
        }
    }

    #[Bench\Revs(3)]
    public function benchExtract1000000Objects(): void
    {
        $object = new ProfileCreated(
            ProfileId::fromString('1'),
            'foo',
            [
                new Skill('php'),
                new Skill('symfony'),
            ],
        );

        for ($i = 0; $i < 1_000_000; $i++) {
            $this->hydrator->serializeObject($object);
        }
    }
}
