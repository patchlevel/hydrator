<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark;

use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerWarmer;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Benchmark\Fixture\Skill;
use PhpBench\Attributes as Bench;

#[Bench\BeforeClassMethods('warmup')]
#[Bench\BeforeMethods('setUp')]
final class GeneratedHydratorBench
{
    private const CACHE_PATH = __DIR__ . '/../../var/cache';

    private StackHydrator $hydrator;

    public function __construct()
    {
        $this->hydrator = self::hydrator();
    }

    /** Runs once before the benchmarks, not in every iteration. */
    public static function warmup(): void
    {
        (new GeneratedTransformerWarmer(self::hydrator(), self::CACHE_PATH))->warmup([ProfileCreated::class]);
    }

    private static function hydrator(): StackHydrator
    {
        return (new StackHydratorBuilder())
            ->useExtension(new GeneratedTransformerExtension(self::CACHE_PATH))
            ->build();
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

    #[Bench\Revs(1000)]
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

    #[Bench\Revs(1000)]
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
}
