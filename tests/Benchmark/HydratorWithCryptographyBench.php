<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark;

use Patchlevel\Hydrator\Cryptography\Cryptographer;
use Patchlevel\Hydrator\Cryptography\CryptographyMetadataFactory;
use Patchlevel\Hydrator\Cryptography\CryptographyMiddleware;
use Patchlevel\Hydrator\Cryptography\Store\InMemoryCipherKeyStore;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\MetadataHydrator;
use Patchlevel\Hydrator\Middleware\TransformMiddleware;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\Skill;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods('setUp')]
final class HydratorWithCryptographyBench
{
    private InMemoryCipherKeyStore $store;

    private Hydrator $hydrator;

    public function __construct()
    {
        $this->store = new InMemoryCipherKeyStore();

        $this->hydrator = new MetadataHydrator(
            new CryptographyMetadataFactory(
                Cryptographer::createWithOpenssl($this->store),
                new AttributeMetadataFactory(),
            ),
            [
                new CryptographyMiddleware(),
                new TransformMiddleware(),
            ],
        );
    }

    public function setUp(): void
    {
        $this->store->clear();

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
        $this->hydrator->hydrate(
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

        $this->hydrator->extract($object);
    }

    #[Bench\Revs(3)]
    public function benchHydrate1000Objects(): void
    {
        for ($i = 0; $i < 1_000; $i++) {
            $this->hydrator->hydrate(
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
            $this->hydrator->extract($object);
        }
    }

    #[Bench\Revs(3)]
    public function benchHydrate1000000Objects(): void
    {
        for ($i = 0; $i < 1_000_000; $i++) {
            $this->hydrator->hydrate(
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
            $this->hydrator->extract($object);
        }
    }
}
