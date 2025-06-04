<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark;

use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\LazyHydrator;
use Patchlevel\Hydrator\MetadataHydrator;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileCreated;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods('setUp')]
final class LazyHydratorBench
{
    private Hydrator $hydrator;

    public function __construct()
    {
        $this->hydrator = new LazyHydrator(MetadataHydrator::create());
    }

    public function setUp(): void
    {
        $object = $this->hydrator->hydrate(
            ProfileCreated::class,
            [
                'profileId' => '1',
                'name' => 'foo',
                'skills' => [
                    ['name' => 'php'],
                    ['name' => 'symfony'],
                ],
            ],
        );

        $this->hydrator->extract($object);
    }

    #[Bench\Revs(5)]
    public function benchHydrate1Object(): void
    {
        $this->hydrator->hydrate(ProfileCreated::class, [
            'profileId' => '1',
            'name' => 'foo',
            'skills' => [
                ['name' => 'php'],
                ['name' => 'symfony'],
            ],
        ]);
    }

    #[Bench\Revs(5)]
    public function benchHydrate1ObjectTriggerInit(): void
    {
        $object = $this->hydrator->hydrate(ProfileCreated::class, [
            'profileId' => '1',
            'name' => 'foo',
            'skills' => [
                ['name' => 'php'],
                ['name' => 'symfony'],
            ],
        ]);

        $name = $object->name;
    }

    #[Bench\Revs(3)]
    public function benchHydrate1000Objects(): void
    {
        for ($i = 0; $i < 1_000; $i++) {
            $this->hydrator->hydrate(ProfileCreated::class, [
                'profileId' => '1',
                'name' => 'foo',
                'skills' => [
                    ['name' => 'php'],
                    ['name' => 'symfony'],
                ],
            ]);
        }
    }

    #[Bench\Revs(3)]
    public function benchHydrate1000ObjectsTriggerInit(): void
    {
        for ($i = 0; $i < 1_000; $i++) {
            $object = $this->hydrator->hydrate(ProfileCreated::class, [
                'profileId' => '1',
                'name' => 'foo',
                'skills' => [
                    ['name' => 'php'],
                    ['name' => 'symfony'],
                ],
            ]);

            $name = $object->name;
        }
    }

    #[Bench\Revs(3)]
    public function benchHydrate1000000Objects(): void
    {
        for ($i = 0; $i < 1_000_000; $i++) {
            $this->hydrator->hydrate(ProfileCreated::class, [
                'profileId' => '1',
                'name' => 'foo',
                'skills' => [
                    ['name' => 'php'],
                    ['name' => 'symfony'],
                ],
            ]);
        }
    }

    #[Bench\Revs(3)]
    public function benchHydrate1000000ObjectsTriggerInit(): void
    {
        for ($i = 0; $i < 1_000_000; $i++) {
            $object = $this->hydrator->hydrate(ProfileCreated::class, [
                'profileId' => '1',
                'name' => 'foo',
                'skills' => [
                    ['name' => 'php'],
                    ['name' => 'symfony'],
                ],
            ]);

            $object = $object->name;
        }
    }
}
