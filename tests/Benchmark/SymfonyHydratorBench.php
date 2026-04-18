<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark;

use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\Skill;
use PhpBench\Attributes as Bench;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

#[Bench\BeforeMethods('setUp')]
final class SymfonyHydratorBench
{
    private Serializer $hydrator;

    public function __construct()
    {
        $this->hydrator = new Serializer([new ObjectNormalizer()], []);
    }

    public function setUp(): void
    {
        $object = $this->hydrator->denormalize(
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

        $this->hydrator->normalize($object);
    }

    #[Bench\Revs(5)]
    public function benchHydrate1Object(): void
    {
        $this->hydrator->denormalize(
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

        $this->hydrator->normalize($object);
    }

    #[Bench\Revs(3)]
    public function benchHydrate1000Objects(): void
    {
        for ($i = 0; $i < 1_000; $i++) {
            $this->hydrator->denormalize(
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
            $this->hydrator->normalize($object);
        }
    }

    #[Bench\Revs(3)]
    public function benchHydrate1000000Objects(): void
    {
        for ($i = 0; $i < 1_000_000; $i++) {
            $this->hydrator->denormalize(
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
            $this->hydrator->normalize($object);
        }
    }
}
