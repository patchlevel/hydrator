<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use DateTimeInterface;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerFactory;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerNotWritable;
use Patchlevel\Hydrator\Extension\Generated\TransformerFiles;
use Patchlevel\Hydrator\Extension\Tracing\TracingExtension;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Extension\Tracing\Fixture\RecordingTracer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferredDateDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\LifecycleFixture;
use Patchlevel\Hydrator\Tests\Unit\Fixture\NestedLifecycleDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Transformer\ReflectionTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function clearstatcache;
use function filemtime;
use function glob;
use function sys_get_temp_dir;
use function touch;
use function uniqid;

#[CoversClass(GeneratedTransformerExtension::class)]
#[CoversClass(GeneratedTransformerFactory::class)]
#[CoversClass(TransformerFiles::class)]
final class GeneratedTransformerExtensionTest extends TestCase
{
    public function testRegistersTransformerFactory(): void
    {
        $builder = (new StackHydratorBuilder())->useExtension(new GeneratedTransformerExtension('/tmp'));

        self::assertCount(1, $builder->transformerFactories());
        self::assertInstanceOf(GeneratedTransformerFactory::class, $builder->transformerFactories()[0]);
        self::assertSame([], $builder->decorators());
    }

    public function testGeneratedFileIsWrittenAndReused(): void
    {
        $path = self::uniquePath();
        $field = uniqid('email_');

        $hydrator = self::hydrator(new GeneratedTransformerExtension($path, autoGenerate: true), $field);
        $event = $hydrator->hydrate(ProfileCreated::class, ['profileId' => '1', $field => 'info@patchlevel.de']);

        self::assertEquals(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')), $event);

        $files = glob($path . '/ProfileCreatedTransformer_*.php');
        self::assertIsArray($files);
        self::assertCount(1, $files);

        $file = $files[0];
        $mtime = filemtime($file);
        self::assertIsInt($mtime);
        touch($file, $mtime - 100);

        // the class is already loaded, the file is neither generated nor required again
        $hydrator = self::hydrator(new GeneratedTransformerExtension($path, autoGenerate: true), $field);
        $hydrator->hydrate(ProfileCreated::class, ['profileId' => '1', $field => 'info@patchlevel.de']);

        clearstatcache(true, $file);
        self::assertSame($mtime - 100, filemtime($file));
        self::assertNotInstanceOf(ReflectionTransformer::class, $hydrator->transformer($hydrator->metadata(ProfileCreated::class)));
    }

    public function testNothingIsGeneratedWithoutAutoGenerate(): void
    {
        $path = self::uniquePath();

        $field = uniqid('email_');

        $hydrator = self::hydrator(new GeneratedTransformerExtension($path), $field);
        $hydrator->hydrate(ProfileCreated::class, ['profileId' => '1', $field => 'info@patchlevel.de']);

        self::assertInstanceOf(ReflectionTransformer::class, $hydrator->transformer($hydrator->metadata(ProfileCreated::class)));
        self::assertDirectoryDoesNotExist($path);
    }

    public function testAnonymousClassesAreTransformedWithReflection(): void
    {
        $object = new class {
            public string $name = 'foo';
        };

        $hydrator = self::hydrator(new GeneratedTransformerExtension(self::uniquePath(), autoGenerate: true));

        self::assertSame(['name' => 'foo'], $hydrator->extract($object));
        self::assertInstanceOf(ReflectionTransformer::class, $hydrator->transformer($hydrator->metadata($object::class)));
    }

    public function testNoTransformerForClassesWhichAreNeverTransformed(): void
    {
        $hydrator = self::hydrator(new GeneratedTransformerExtension(self::uniquePath(), autoGenerate: true));
        $factory = new GeneratedTransformerFactory(new TransformerFiles(self::uniquePath()), true);

        // a class normalizer handles the class, the hydrator never asks for its transformer
        self::assertNull($factory->create($hydrator->metadata(ProfileId::class), $hydrator));
    }

    public function testBuildIsSupported(): void
    {
        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new GeneratedTransformerExtension(GeneratedStackHydratorTest::cachePath(), autoGenerate: true))
            ->build();

        $event = $hydrator->hydrate(ProfileCreated::class, ['profileId' => '1', 'email' => 'info@patchlevel.de']);

        self::assertEquals(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')), $event);
        self::assertNotInstanceOf(ReflectionTransformer::class, $hydrator->transformer($hydrator->metadata(ProfileCreated::class)));
    }

    public function testTracingSeesNestedObjects(): void
    {
        $tracer = new RecordingTracer();

        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new GeneratedTransformerExtension(GeneratedStackHydratorTest::cachePath(), autoGenerate: true))
            ->useExtension(new TracingExtension($tracer))
            ->buildHydrator();

        $data = ['child' => ['name' => 'a'], 'items' => [['name' => 'b']]];
        self::assertSame($data, $hydrator->extract($hydrator->hydrate(NestedLifecycleDto::class, $data)));

        // nested objects are not inlined, so they go through the tracing as well
        self::assertSame(
            [
                'hydrate ' . NestedLifecycleDto::class,
                'hydrate ' . LifecycleFixture::class,
                'hydrate ' . LifecycleFixture::class,
                'extract ' . NestedLifecycleDto::class,
                'extract ' . LifecycleFixture::class,
                'extract ' . LifecycleFixture::class,
            ],
            $tracer->traces,
        );
    }

    public function testCodeIsGeneratedWithTheCompleteMetadataFactory(): void
    {
        // the extension is registered before the CoreExtension, so its guesser for the date is not known yet
        // when the extension is configured. The code is generated with the metadata of the hydrator.
        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new GeneratedTransformerExtension(GeneratedStackHydratorTest::cachePath(), autoGenerate: true))
            ->useExtension(new CoreExtension())
            ->buildHydrator();

        $data = ['createdAt' => '2024-05-04T10:15:30+00:00'];
        $object = $hydrator->hydrate(InferredDateDto::class, $data);

        self::assertSame('2024-05-04T10:15:30+00:00', $object->createdAt->format(DateTimeInterface::ATOM));
        self::assertSame($data, $hydrator->extract($object));
    }

    public function testNotWritable(): void
    {
        $file = sys_get_temp_dir() . '/' . uniqid('not-a-directory');
        touch($file);

        $field = uniqid('email_');
        $hydrator = self::hydrator(new GeneratedTransformerExtension($file, autoGenerate: true), $field);

        $this->expectException(GeneratedTransformerNotWritable::class);

        $hydrator->hydrate(ProfileCreated::class, ['profileId' => '1', $field => 'info@patchlevel.de']);
    }

    /**
     * Classes are loaded once per process, renaming the email field to a unique name gives the class a fingerprint
     * no transformer was loaded for yet.
     */
    private static function hydrator(GeneratedTransformerExtension $extension, string|null $emailField = null): StackHydrator
    {
        $builder = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension($extension);

        if ($emailField !== null) {
            $builder->addMetadataEnricher(new class ($emailField) implements MetadataEnricher {
                public function __construct(private readonly string $field)
                {
                }

                public function enrich(ClassMetadata $classMetadata): void
                {
                    $property = $classMetadata->properties['email'] ?? null;

                    if ($property === null) {
                        return;
                    }

                    $property->fieldName = $this->field;
                }
            });
        }

        $hydrator = $builder->buildHydrator();
        self::assertInstanceOf(StackHydrator::class, $hydrator);

        return $hydrator;
    }

    private static function uniquePath(): string
    {
        return GeneratedStackHydratorTest::cachePath() . '/' . uniqid('cache', true);
    }
}
