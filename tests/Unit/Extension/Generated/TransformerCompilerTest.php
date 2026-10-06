<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Generated\ClassNotGeneratable;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerExtension;
use Patchlevel\Hydrator\Extension\Generated\TransformerCompiler;
use Patchlevel\Hydrator\Extension\Generated\TransformerFiles;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Fixture\LifecycleFixture;
use Patchlevel\Hydrator\Tests\Unit\Fixture\NestedLifecycleDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Transformer\ReflectionTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function glob;
use function sort;
use function uniqid;

#[CoversClass(TransformerCompiler::class)]
#[CoversClass(TransformerFiles::class)]
final class TransformerCompilerTest extends TestCase
{
    public function testCompileClassesWithNestedClasses(): void
    {
        $path = GeneratedStackHydratorTest::cachePath() . '/' . uniqid('compiled', true);
        $field = uniqid('name_');
        $builder = self::builder($path, $field);

        $compiled = (new TransformerCompiler($builder->metadataFactory(), $path))->compile([
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
        $hydrator = $builder->buildHydrator();
        self::assertInstanceOf(StackHydrator::class, $hydrator);

        $data = ['child' => [$field => 'a'], 'items' => [[$field => 'b']]];
        self::assertSame($data, $hydrator->extract($hydrator->hydrate(NestedLifecycleDto::class, $data)));
        self::assertNotInstanceOf(ReflectionTransformer::class, $hydrator->transformer($hydrator->metadata(NestedLifecycleDto::class)));
        self::assertNotInstanceOf(ReflectionTransformer::class, $hydrator->transformer($hydrator->metadata(LifecycleFixture::class)));
    }

    public function testUnknownClassIsNotGeneratable(): void
    {
        $path = GeneratedStackHydratorTest::cachePath() . '/' . uniqid('compiled', true);

        $this->expectException(ClassNotGeneratable::class);

        /** @phpstan-ignore argument.type */
        (new TransformerCompiler(self::builder($path, uniqid('name_'))->metadataFactory(), $path))->compile(['Unknown']);
    }

    public function testCompileWithoutClasses(): void
    {
        $path = GeneratedStackHydratorTest::cachePath() . '/' . uniqid('compiled', true);

        self::assertSame([], (new TransformerCompiler(self::builder($path, uniqid('name_'))->metadataFactory(), $path))->compile([]));
        self::assertSame([], glob($path . '/*.php'));
    }

    /** A unique field name gives the classes fingerprints no transformer was loaded for yet in this process. */
    private static function builder(string $path, string $field): StackHydratorBuilder
    {
        return (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
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
