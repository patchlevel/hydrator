<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark;

use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\Skill;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods('setUp')]
final class JMSHydratorBench
{
    private Serializer $hydrator;

    public function __construct()
    {
        $this->hydrator = SerializerBuilder::create()->build();
    }

    public function setUp(): void
    {
        $object = $this->hydrator->fromArray(
            [
                'profileId' => '1',
                'name' => 'foo',
                'skills' => [
                    ['name' => 'php'],
                    ['name' => 'symfony'],
                ],
            ],
            ProfileCreated::class,
        );

        $this->hydrator->toArray($object);
    }

    #[Bench\Revs(5)]
    public function benchHydrate1Object(): void
    {
        $this->hydrator->fromArray(
            [
                'profileId' => '1',
                'name' => 'foo',
                'skills' => [
                    ['name' => 'php'],
                    ['name' => 'symfony'],
                ],
            ],
            ProfileCreated::class
        );
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

        $this->hydrator->toArray($object);
    }

    #[Bench\Revs(3)]
    public function benchHydrate1000Objects(): void
    {
        for ($i = 0; $i < 1_000; $i++) {
            $this->hydrator->fromArray(
                [
                    'profileId' => '1',
                    'name' => 'foo',
                    'skills' => [
                        ['name' => 'php'],
                        ['name' => 'symfony'],
                    ],
                ],
                ProfileCreated::class
            );
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
            $this->hydrator->toArray($object);
        }
    }

    #[Bench\Revs(3)]
    public function benchHydrate1000000Objects(): void
    {
        for ($i = 0; $i < 1_000_000; $i++) {
            $this->hydrator->fromArray(
                [
                    'profileId' => '1',
                    'name' => 'foo',
                    'skills' => [
                        ['name' => 'php'],
                        ['name' => 'symfony'],
                    ],
                ],
                ProfileCreated::class
            );
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
            $this->hydrator->toArray($object);
        }
    }
}
