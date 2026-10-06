<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use Patchlevel\Hydrator\Extension\Generated\ClassNotGeneratable;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformer;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerFactory;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerNotWritable;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerWarmer;
use Patchlevel\Hydrator\Extension\Generated\TransformerFiles;
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

#[CoversClass(GeneratedTransformerWarmer::class)]
#[CoversClass(GeneratedTransformerExtension::class)]
#[CoversClass(GeneratedTransformerFactory::class)]
#[CoversClass(TransformerFiles::class)]
final class GeneratedTransformerWarmerTest extends TestCase
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
        $warmer = new GeneratedTransformerWarmer($metadataFactory, $this->cachePath);

        self::assertSame(
            [ProfileCreatedWrapper::class, ProfileCreated::class],
            $warmer->warmup([ProfileCreatedWrapper::class]),
        );

        $files = new TransformerFiles($this->cachePath);

        foreach ([ProfileCreatedWrapper::class, ProfileCreated::class] as $class) {
            self::assertFileExists($files->path($files->className($metadataFactory->metadata($class))));
        }
    }

    public function testWarmupIsDeterministic(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $files = new TransformerFiles($this->cachePath);
        $file = $files->path($files->className($metadataFactory->metadata(ProfileCreated::class)));

        (new GeneratedTransformerWarmer($metadataFactory, $this->cachePath))->warmup([ProfileCreated::class]);
        $first = file_get_contents($file);

        (new GeneratedTransformerWarmer($metadataFactory, $this->cachePath))->warmup([ProfileCreated::class]);

        self::assertSame($first, file_get_contents($file));
    }

    public function testWarmedTransformersAreUsed(): void
    {
        $builder = (new StackHydratorBuilder())->useExtension(new GeneratedTransformerExtension($this->cachePath));
        (new GeneratedTransformerWarmer($builder->metadataFactory(), $this->cachePath))->warmup([ProfileCreatedWrapper::class]);
        $hydrator = $builder->build();

        $data = ['event' => ['profileId' => '1', 'email' => 'info@patchlevel.de']];
        $object = $hydrator->hydrate(ProfileCreatedWrapper::class, $data);

        self::assertEquals(
            new ProfileCreatedWrapper(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de'))),
            $object,
        );
        self::assertSame($data, $hydrator->extract($object));

        $transformers = (new ReflectionProperty(StackHydrator::class, 'transformers'))->getValue($hydrator);
        self::assertIsArray($transformers);
        self::assertInstanceOf(GeneratedTransformer::class, $transformers[ProfileCreatedWrapper::class]);
        self::assertInstanceOf(GeneratedTransformer::class, $transformers[ProfileCreated::class]);
    }

    public function testUnknownClassIsNotGeneratable(): void
    {
        $this->expectException(ClassNotGeneratable::class);

        /** @phpstan-ignore argument.type */
        (new GeneratedTransformerWarmer(new AttributeMetadataFactory(), $this->cachePath))->warmup(['Unknown']);
    }

    public function testAbstractClassIsNotGeneratable(): void
    {
        $this->expectException(ClassNotGeneratable::class);

        (new GeneratedTransformerWarmer(new AttributeMetadataFactory(), $this->cachePath))->warmup([ChildDto::class]);
    }

    public function testClassWithClassNormalizerIsNotGeneratable(): void
    {
        $this->expectException(ClassNotGeneratable::class);

        (new GeneratedTransformerWarmer(new AttributeMetadataFactory(), $this->cachePath))->warmup([ProfileId::class]);
    }

    public function testNotWritable(): void
    {
        $this->expectException(GeneratedTransformerNotWritable::class);

        $file = $this->cachePath . '-file';
        touch($file);

        try {
            (new GeneratedTransformerWarmer(new AttributeMetadataFactory(), $file))->warmup([ProfileCreated::class]);
        } finally {
            unlink($file);
        }
    }
}
