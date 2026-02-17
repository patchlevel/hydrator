<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark;

use Crell\AttributeUtils\Analyzer;
use Crell\AttributeUtils\MemoryCacheAnalyzer;
use Crell\Serde\Serde;
use Crell\Serde\SerdeCommon;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\Skill;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods('setUp')]
final class SerdeHydratorBench
{
    private Serde $hydrator;

    public function __construct()
    {
        $analyzer = new MemoryCacheAnalyzer(new Analyzer());
        $this->hydrator = new SerdeCommon($analyzer);
    }

    public function setUp(): void
    {
        $object = $this->hydrator->deserialize(
            [
                'profile_id' => '1',
                'name' => 'foo',
                'skills' => [
                    ['name' => 'php'],
                    ['name' => 'symfony'],
                ],
            ],
            'array',
            ProfileCreated::class,
        );

        $this->hydrator->serialize($object, 'array');
    }

    #[Bench\Revs(5)]
    public function benchHydrate1Object(): void
    {
        $this->hydrator->deserialize(
            [
                'profile_id' => '1',
                'name' => 'foo',
                'skills' => [
                    ['name' => 'php'],
                    ['name' => 'symfony'],
                ],
            ],
            'array',
            ProfileCreated::class,
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

        $this->hydrator->serialize($object, 'array');
    }

    #[Bench\Revs(3)]
    public function benchHydrate1000Objects(): void
    {
        for ($i = 0; $i < 1_000; $i++) {
            $this->hydrator->deserialize(
                [
                    'profile_id' => '1',
                    'name' => 'foo',
                    'skills' => [
                        ['name' => 'php'],
                        ['name' => 'symfony'],
                    ],
                ],
                'array',
                ProfileCreated::class,
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
            $this->hydrator->serialize($object, 'array');
        }
    }

    #[Bench\Revs(3)]
    public function benchHydrate1000000Objects(): void
    {
        for ($i = 0; $i < 1_000_000; $i++) {
            $this->hydrator->deserialize(
                [
                    'profile_id' => '1',
                    'name' => 'foo',
                    'skills' => [
                        ['name' => 'php'],
                        ['name' => 'symfony'],
                    ],
                ],
                'array',
                ProfileCreated::class,
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
            $this->hydrator->serialize($object, 'array');
        }
    }
}
