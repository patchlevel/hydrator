<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark;

use Patchlevel\Hydrator\Cryptography\PersonalDataPayloadCryptographer;
use Patchlevel\Hydrator\Cryptography\Store\InMemoryCipherKeyStore;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\MetadataHydrator;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\PersonalDataProfileCreated;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileId;
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
            cryptographer: PersonalDataPayloadCryptographer::createWithOpenssl($this->store),
        );
    }

    public function setUp(): void
    {
        $this->store->clear();

        $object = $this->hydrator->hydrate(PersonalDataProfileCreated::class, [
            'profileId' => '1',
            'name' => 'foo',
        ]);

        $this->hydrator->extract($object);
    }

    #[Bench\Revs(10)]
    public function benchHydrate1Object(): void
    {
        $this->hydrator->hydrate(PersonalDataProfileCreated::class, [
            'profileId' => '1',
            'name' => 'foo',
        ]);
    }

    #[Bench\Revs(10)]
    public function benchExtract1Object(): void
    {
        $object = new PersonalDataProfileCreated(ProfileId::fromString('1'), 'foo');

        $this->hydrator->extract($object);
    }

    #[Bench\Revs(10)]
    public function benchHydrate1000Objects(): void
    {
        for ($i = 0; $i < 1_000; $i++) {
            $this->hydrator->hydrate(PersonalDataProfileCreated::class, [
                'profileId' => '1',
                'name' => 'foo',
            ]);
        }
    }

    #[Bench\Revs(10)]
    public function benchExtract1000Objects(): void
    {
        $object = new PersonalDataProfileCreated(ProfileId::fromString('1'), 'foo');

        for ($i = 0; $i < 1_000; $i++) {
            $this->hydrator->extract($object);
        }
    }

    #[Bench\Revs(10)]
    public function benchHydrate1000000Objects(): void
    {
        for ($i = 0; $i < 1_000_000; $i++) {
            $this->hydrator->hydrate(PersonalDataProfileCreated::class, [
                'profileId' => '1',
                'name' => 'foo',
            ]);
        }
    }

    #[Bench\Revs(10)]
    public function benchExtract1000000Objects(): void
    {
        $object = new PersonalDataProfileCreated(ProfileId::fromString('1'), 'foo');

        for ($i = 0; $i < 1_000_000; $i++) {
            $this->hydrator->extract($object);
        }
    }
}
