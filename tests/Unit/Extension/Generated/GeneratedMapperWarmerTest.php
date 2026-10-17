<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use Patchlevel\Hydrator\Extension\Generated\ClassNotGeneratable;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapper;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperFactory;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperNotWritable;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperWarmer;
use Patchlevel\Hydrator\Extension\Generated\MapperFiles;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ChildDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreatedWrapper;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function file_get_contents;
use function glob;
use function is_dir;
use function rmdir;
use function sys_get_temp_dir;
use function touch;
use function uniqid;
use function unlink;

#[CoversClass(GeneratedMapperWarmer::class)]
#[CoversClass(GeneratedMapperExtension::class)]
#[CoversClass(GeneratedMapperFactory::class)]
#[CoversClass(MapperFiles::class)]
final class GeneratedMapperWarmerTest extends TestCase
{
    private string $cachePath;

    public function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/' . uniqid('patchlevel-hydrator-warmer-', true);
    }

    public function tearDown(): void
    {
        if (!is_dir($this->cachePath)) {
            return;
        }

        foreach (glob($this->cachePath . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->cachePath);
    }

    public function testWarmupGeneratesNestedClasses(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $warmer = new GeneratedMapperWarmer($metadataFactory, $this->cachePath);

        self::assertSame(
            [ProfileCreatedWrapper::class, ProfileCreated::class],
            $warmer->warmup([ProfileCreatedWrapper::class]),
        );

        $files = new MapperFiles($this->cachePath);

        foreach ([ProfileCreatedWrapper::class, ProfileCreated::class] as $class) {
            self::assertFileExists($files->path($files->className($metadataFactory->metadata($class))));
        }
    }

    public function testWarmupIsDeterministic(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $files = new MapperFiles($this->cachePath);
        $file = $files->path($files->className($metadataFactory->metadata(ProfileCreated::class)));

        (new GeneratedMapperWarmer($metadataFactory, $this->cachePath))->warmup([ProfileCreated::class]);
        $first = file_get_contents($file);

        (new GeneratedMapperWarmer($metadataFactory, $this->cachePath))->warmup([ProfileCreated::class]);

        self::assertSame($first, file_get_contents($file));
    }

    public function testWarmedMappersAreUsed(): void
    {
        $builder = (new StackHydratorBuilder())->useExtension(new GeneratedMapperExtension($this->cachePath));
        (new GeneratedMapperWarmer($builder->metadataFactory(), $this->cachePath))->warmup([ProfileCreatedWrapper::class]);
        $hydrator = $builder->build();

        $data = ['event' => ['profileId' => '1', 'email' => 'info@patchlevel.de']];
        $object = $hydrator->hydrate(ProfileCreatedWrapper::class, $data);

        self::assertEquals(
            new ProfileCreatedWrapper(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de'))),
            $object,
        );
        self::assertSame($data, $hydrator->extract($object));

        $mappers = (new ReflectionProperty(StackHydrator::class, 'mappers'))->getValue($hydrator);
        self::assertIsArray($mappers);
        self::assertInstanceOf(GeneratedMapper::class, $mappers[ProfileCreatedWrapper::class]);
        self::assertInstanceOf(GeneratedMapper::class, $mappers[ProfileCreated::class]);
    }

    public function testUnknownClassIsNotGeneratable(): void
    {
        $this->expectException(ClassNotGeneratable::class);

        /** @phpstan-ignore argument.type */
        (new GeneratedMapperWarmer(new AttributeMetadataFactory(), $this->cachePath))->warmup(['Unknown']);
    }

    public function testAbstractClassIsNotGeneratable(): void
    {
        $this->expectException(ClassNotGeneratable::class);

        (new GeneratedMapperWarmer(new AttributeMetadataFactory(), $this->cachePath))->warmup([ChildDto::class]);
    }

    public function testClassWithClassNormalizerIsNotGeneratable(): void
    {
        $this->expectException(ClassNotGeneratable::class);

        (new GeneratedMapperWarmer(new AttributeMetadataFactory(), $this->cachePath))->warmup([ProfileId::class]);
    }

    public function testNotWritable(): void
    {
        $this->expectException(GeneratedMapperNotWritable::class);

        $file = $this->cachePath . '-file';
        touch($file);

        try {
            (new GeneratedMapperWarmer(new AttributeMetadataFactory(), $file))->warmup([ProfileCreated::class]);
        } finally {
            unlink($file);
        }
    }
}
