<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use Patchlevel\Hydrator\Extension\Generated\ClassNotGeneratable;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerNotWritable;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerWarmer;
use Patchlevel\Hydrator\Extension\Generated\TransformerFiles;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Fixture\LifecycleFixture;
use Patchlevel\Hydrator\Tests\Unit\Fixture\NestedLifecycleDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use Patchlevel\Hydrator\Transformer\ReflectionTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function glob;
use function sort;
use function sys_get_temp_dir;
use function touch;
use function uniqid;

#[CoversClass(GeneratedTransformerWarmer::class)]
#[CoversClass(TransformerFiles::class)]
final class GeneratedTransformerWarmerTest extends TestCase
{
    public function testWarmupClassesWithNestedClasses(): void
    {
        $path = GeneratedStackHydratorTest::cachePath() . '/' . uniqid('compiled', true);
        $field = uniqid('name_');
        $builder = self::builder($path, $field);

        $compiled = (new GeneratedTransformerWarmer($builder->build(), $path))->warmup([
            '\\' . NestedLifecycleDto::class,
            NestedLifecycleDto::class,
            ProfileId::class,
        ]);

        self::assertSame([NestedLifecycleDto::class, LifecycleFixture::class], $compiled);

        $files = glob($path . '/*.php') ?: [];
        sort($files);
        self::assertCount(2, $files);
        self::assertStringContainsString('/LifecycleFixtureTransformer_', $files[0]);
        self::assertStringContainsString('/NestedLifecycleDtoTransformer_', $files[1]);

        // the hydrator picks them up without generating anything itself
        $hydrator = $builder->build();

        $data = ['child' => [$field => 'a'], 'items' => [[$field => 'b']]];
        self::assertSame($data, $hydrator->extract($hydrator->hydrate(NestedLifecycleDto::class, $data)));
        self::assertNotInstanceOf(ReflectionTransformer::class, self::transformer($hydrator, NestedLifecycleDto::class));
        self::assertNotInstanceOf(ReflectionTransformer::class, self::transformer($hydrator, LifecycleFixture::class));
    }

    public function testUnknownClassIsNotGeneratable(): void
    {
        $path = GeneratedStackHydratorTest::cachePath() . '/' . uniqid('compiled', true);

        $this->expectException(ClassNotGeneratable::class);

        /** @phpstan-ignore argument.type */
        (new GeneratedTransformerWarmer(self::builder($path, uniqid('name_'))->build(), $path))->warmup(['Unknown']);
    }

    public function testWarmupWithoutClasses(): void
    {
        $path = GeneratedStackHydratorTest::cachePath() . '/' . uniqid('compiled', true);

        self::assertSame([], (new GeneratedTransformerWarmer(self::builder($path, uniqid('name_'))->build(), $path))->warmup([]));
        self::assertSame([], glob($path . '/*.php'));
    }

    public function testNotWritable(): void
    {
        $file = sys_get_temp_dir() . '/' . uniqid('not-a-directory');
        touch($file);

        $this->expectException(GeneratedTransformerNotWritable::class);

        (new GeneratedTransformerWarmer(self::builder($file, uniqid('name_'))->build(), $file))->warmup([NestedLifecycleDto::class]);
    }

    /** @param class-string $class */
    private static function transformer(StackHydrator $hydrator, string $class): ClassTransformer
    {
        $transformer = (new ReflectionMethod(StackHydrator::class, 'transformer'))->invoke($hydrator, $hydrator->metadata($class));
        self::assertInstanceOf(ClassTransformer::class, $transformer);

        return $transformer;
    }

    /** A unique field name gives the classes fingerprints no transformer was loaded for yet in this process. */
    private static function builder(string $path, string $field): StackHydratorBuilder
    {
        return (new StackHydratorBuilder())
            ->useExtension(new GeneratedTransformerExtension($path))
            ->addMetadataEnricher(new class ($field) implements MetadataEnricher {
                public function __construct(private readonly string $field)
                {
                }

                public function enrich(ClassMetadata $classMetadata): void
                {
                    if ($classMetadata->className !== LifecycleFixture::class) {
                        return;
                    }

                    $property = $classMetadata->properties['name'];
                    $property->fieldName = $this->field;
                }
            });
    }
}
