<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Generated\ClassFingerprint;
use Patchlevel\Hydrator\Extension\Generated\ClassNotGeneratable;
use Patchlevel\Hydrator\Extension\Generated\ClassPlan;
use Patchlevel\Hydrator\Extension\Generated\ClassPlanner;
use Patchlevel\Hydrator\Extension\Generated\CodeEmitter;
use Patchlevel\Hydrator\Extension\Generated\GeneratedCode;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerExtension;
use Patchlevel\Hydrator\Extension\Generated\Literal;
use Patchlevel\Hydrator\Extension\Generated\OutdatedGeneratedTransformer;
use Patchlevel\Hydrator\Extension\Generated\PropertyPlan;
use Patchlevel\Hydrator\Extension\Generated\Templates;
use Patchlevel\Hydrator\Extension\Generated\TransformerFiles;
use Patchlevel\Hydrator\Extension\Generated\TransformerGenerator;
use Patchlevel\Hydrator\Extension\Generated\ValueKind;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ChildDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\FingerprintChangedDefaultDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\FingerprintChangedVisibilityDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\FingerprintDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\LifecycleFixture;
use Patchlevel\Hydrator\Tests\Unit\Fixture\MultilineDefaultDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\NestedLifecycleDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ScalarAndNestedDto;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use Patchlevel\Hydrator\Transformer\ClassTransformerFactory;
use Patchlevel\Hydrator\Transformer\ReflectionTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_filter;
use function assert;
use function file_put_contents;
use function get_object_vars;
use function getenv;
use function glob;
use function is_dir;
use function mkdir;
use function preg_match;
use function sprintf;
use function tempnam;
use function unlink;

use const ARRAY_FILTER_USE_KEY;

#[CoversClass(TransformerGenerator::class)]
#[CoversClass(GeneratedCode::class)]
#[CoversClass(ClassPlanner::class)]
#[CoversClass(ClassFingerprint::class)]
#[CoversClass(Literal::class)]
#[CoversClass(ClassPlan::class)]
#[CoversClass(PropertyPlan::class)]
#[CoversClass(ValueKind::class)]
#[CoversClass(CodeEmitter::class)]
#[CoversClass(Templates::class)]
final class TransformerGeneratorTest extends TestCase
{
    private static int $counter = 0;

    public function testGenerateIsDeterministic(): void
    {
        $generator = new TransformerGenerator(new AttributeMetadataFactory());

        $first = $generator->generate(ProfileCreated::class, 'Foo\\Bar');
        $second = $generator->generate(ProfileCreated::class, 'Foo\\Bar');

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame($first->code, $second->code);
        self::assertSame([ProfileCreated::class], $first->classes);
        self::assertStringContainsString('namespace Foo;', $first->code);
        self::assertStringContainsString('final class Bar implements ClassTransformer', $first->code);
        self::assertStringNotContainsString('strict_types=1', $first->code);
    }

    public function testNestedClassesAreReported(): void
    {
        $code = (new TransformerGenerator((new StackHydratorBuilder())->useExtension(new CoreExtension())->metadataFactory()))
            ->generate(NestedLifecycleDto::class, 'Foo\\Bar');

        self::assertNotNull($code);
        self::assertSame([NestedLifecycleDto::class, LifecycleFixture::class], $code->classes);
    }

    /**
     * The snapshots make every change of the generated code visible in the diff of a pull request.
     * Run "make snapshot" after an intended change of the generator.
     */
    #[RequiresPhp('>=8.5')]
    public function testGeneratedCodeMatchesSnapshot(): void
    {
        $generator = new TransformerGenerator(new AttributeMetadataFactory());
        $update = getenv('UPDATE_SNAPSHOTS') === '1';

        if ($update) {
            foreach (glob(__DIR__ . '/Snapshot/*.php') ?: [] as $file) {
                unlink($file);
            }

            if (!is_dir(__DIR__ . '/Snapshot')) {
                mkdir(__DIR__ . '/Snapshot');
            }
        }

        foreach (GeneratedStackHydratorTest::CLASSES as $class) {
            $name = (new ReflectionClass($class))->getShortName() . 'Transformer';
            $code = $generator->generate($class, 'Patchlevel\\Hydrator\\Tests\\Snapshot\\' . $name);

            if ($code === null) {
                continue;
            }

            $file = sprintf('%s/Snapshot/%s.php', __DIR__, $name);

            if ($update) {
                file_put_contents($file, $code->code);
            }

            self::assertStringEqualsFile($file, $code->code, 'The generated code changed, run "make snapshot" if the change is intended.');
        }
    }

    public function testClassesWithClassNormalizerAreSkipped(): void
    {
        self::assertNull((new TransformerGenerator(new AttributeMetadataFactory()))->generate(ProfileId::class, 'Foo\\Bar'));
    }

    public function testAbstractClassIsNotGeneratable(): void
    {
        $this->expectException(ClassNotGeneratable::class);

        (new TransformerGenerator(new AttributeMetadataFactory()))->generate(ChildDto::class, 'Foo\\Bar');
    }

    public function testUnknownClassIsNotGeneratable(): void
    {
        $this->expectException(ClassNotGeneratable::class);

        /** @phpstan-ignore argument.type */
        (new TransformerGenerator(new AttributeMetadataFactory()))->generate('Unknown', 'Foo\\Bar');
    }

    public function testDefaultsWithControlCharactersAreInlinedUnchanged(): void
    {
        $hydrator = $this->hydrator(MultilineDefaultDto::class);

        $object = $hydrator->hydrate(MultilineDefaultDto::class, []);

        self::assertSame("foo bar\nbaz", $object->name);
        self::assertSame("first\nsecond", $object->text);
        self::assertSame(["a\tb" => "c\nd \$e \"f\""], $object->lines);
    }

    public function testNestedClassesAreInlined(): void
    {
        $hydrator = $this->hydrator(NestedLifecycleDto::class);
        $data = ['child' => ['name' => 'a'], 'items' => [['name' => 'b']]];

        self::assertSame($data, $hydrator->extract($hydrator->hydrate(NestedLifecycleDto::class, $data)));
        self::assertSame(['ih0' => true, 'ie0' => true, 'ih1' => true, 'ie1' => true], self::inlined($hydrator));
    }

    public function testChangedNestedClassIsNotInlined(): void
    {
        // the nested class changed since the code was generated, the outer class is still the same
        $hydrator = $this->hydrator(NestedLifecycleDto::class, new class implements MetadataEnricher {
            public function enrich(ClassMetadata $classMetadata): void
            {
                if ($classMetadata->className !== LifecycleFixture::class) {
                    return;
                }

                $property = $classMetadata->properties['name'];
                $property->fieldName = 'title';
            }
        });

        $data = ['child' => ['title' => 'a'], 'items' => [['title' => 'b']]];

        self::assertSame($data, $hydrator->extract($hydrator->hydrate(NestedLifecycleDto::class, $data)));
        self::assertSame(['ih0' => false, 'ie0' => false, 'ih1' => false, 'ie1' => false], self::inlined($hydrator));
        self::assertInstanceOf(ReflectionTransformer::class, $hydrator->transformer($hydrator->metadata(LifecycleFixture::class)));
    }

    public function testOutdatedCodeIsDetected(): void
    {
        $this->expectException(OutdatedGeneratedTransformer::class);

        // the normalizer of the property was removed since the code was generated
        $hydrator = $this->hydrator(ProfileCreated::class, self::removeNormalizer());
        $hydrator->hydrate(ProfileCreated::class, ['profileId' => '1', 'email' => 'info@patchlevel.de']);
    }

    public function testOutdatedCodeFallsBackToReflection(): void
    {
        $path = GeneratedStackHydratorTest::cachePath() . '/outdated-' . self::$counter++;
        $files = new TransformerFiles($path);

        // outdated code stored under the name of the current metadata, which the fingerprint normally prevents
        $metadataFactory = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->addMetadataEnricher(self::removeNormalizer())
            ->metadataFactory();

        $className = $files->className($metadataFactory->metadata(ProfileCreated::class), $metadataFactory);
        $code = (new TransformerGenerator(new AttributeMetadataFactory()))->generate(ProfileCreated::class, $files->fqcn($className));
        self::assertNotNull($code);
        $files->write($className, $code->code);

        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new GeneratedTransformerExtension($path))
            ->addMetadataEnricher(self::removeNormalizer())
            ->buildHydrator();
        self::assertInstanceOf(StackHydrator::class, $hydrator);

        $object = $hydrator->hydrate(ProfileCreated::class, ['profileId' => ProfileId::fromString('1'), 'email' => 'info@patchlevel.de']);

        self::assertSame('1', $object->profileId->toString());
        self::assertInstanceOf(ReflectionTransformer::class, $hydrator->transformer($hydrator->metadata(ProfileCreated::class)));
    }

    /** @param class-string $changed a class with the same properties as FingerprintDto but one changed detail */
    #[DataProvider('changedClasses')]
    public function testChangedClassGetsAnotherName(string $changed): void
    {
        $factory = new AttributeMetadataFactory();
        $files = new TransformerFiles('/tmp');

        self::assertNotSame(ClassFingerprint::of($factory->metadata(FingerprintDto::class)), ClassFingerprint::of($factory->metadata($changed)));
        self::assertSame(
            $files->className($factory->metadata(FingerprintDto::class), $factory),
            $files->className((new AttributeMetadataFactory())->metadata(FingerprintDto::class), $factory),
        );
        self::assertNotSame(
            $files->className($factory->metadata(FingerprintDto::class), $factory),
            $files->className($factory->metadata($changed), $factory),
        );
    }

    /** @param class-string $class */
    #[DataProvider('classesWithNestedClass')]
    public function testChangedNestedClassGetsAnotherName(string $class): void
    {
        $factory = (new StackHydratorBuilder())->useExtension(new CoreExtension())->metadataFactory();
        $changed = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->addMetadataEnricher(new class implements MetadataEnricher {
                public function enrich(ClassMetadata $classMetadata): void
                {
                    if ($classMetadata->className !== LifecycleFixture::class) {
                        return;
                    }

                    $property = $classMetadata->properties['name'];
                    $property->fieldName = 'title';
                }
            })
            ->metadataFactory();
        $files = new TransformerFiles('/tmp');

        self::assertSame(ClassFingerprint::of($factory->metadata($class)), ClassFingerprint::of($changed->metadata($class)));
        self::assertNotSame(
            $files->className($factory->metadata($class), $factory),
            $files->className($changed->metadata($class), $changed),
        );
    }

    /** @return iterable<string, array{class-string}> */
    public static function classesWithNestedClass(): iterable
    {
        yield 'nested object first' => [NestedLifecycleDto::class];
        yield 'nested object after a scalar' => [ScalarAndNestedDto::class];
    }

    /** @return iterable<string, array{class-string}> */
    public static function changedClasses(): iterable
    {
        yield 'changed default' => [FingerprintChangedDefaultDto::class];
        yield 'changed visibility' => [FingerprintChangedVisibilityDto::class];
    }

    private static function removeNormalizer(): MetadataEnricher
    {
        return new class implements MetadataEnricher {
            public function enrich(ClassMetadata $classMetadata): void
            {
                if ($classMetadata->className !== ProfileCreated::class) {
                    return;
                }

                $property = $classMetadata->properties['profileId'];
                $property->normalizer = null;
            }
        };
    }

    /**
     * Generates the code for the class with the metadata of a plain hydrator and loads it into a hydrator with the
     * given enrichers, which can simulate classes changing after the code was generated.
     *
     * @param class-string $class
     */
    private function hydrator(string $class, MetadataEnricher ...$enrichers): StackHydrator
    {
        $fqcn = 'Patchlevel\\Hydrator\\Tests\\Generated\\Transformer' . self::$counter++;
        $metadataFactory = (new StackHydratorBuilder())->useExtension(new CoreExtension())->metadataFactory();
        $code = (new TransformerGenerator($metadataFactory))->generate($class, $fqcn);
        self::assertNotNull($code);

        $directory = GeneratedStackHydratorTest::cachePath();

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $file = tempnam($directory, 'transformer');
        assert($file !== false);
        file_put_contents($file, $code->code);

        require $file;
        unlink($file);

        $builder = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->addTransformerFactory(new class ($class, $fqcn) implements ClassTransformerFactory {
                /** @param class-string $class */
                public function __construct(
                    private readonly string $class,
                    private readonly string $fqcn,
                ) {
                }

                public function create(ClassMetadata $metadata, StackHydrator $hydrator): ClassTransformer|null
                {
                    if ($metadata->className !== $this->class) {
                        return null;
                    }

                    $transformer = new ($this->fqcn)($hydrator);
                    assert($transformer instanceof ClassTransformer);

                    return $transformer;
                }
            });

        foreach ($enrichers as $enricher) {
            $builder->addMetadataEnricher($enricher);
        }

        $hydrator = $builder->buildHydrator();
        assert($hydrator instanceof StackHydrator);

        return $hydrator;
    }

    /** @return array<string, bool> flag name => inlined */
    private static function inlined(StackHydrator $hydrator): array
    {
        /** @var array<string, bool> $flags */
        $flags = array_filter(
            get_object_vars($hydrator->transformer($hydrator->metadata(NestedLifecycleDto::class))),
            static fn (string $name): bool => preg_match('/^i[he]\d+$/', $name) === 1,
            ARRAY_FILTER_USE_KEY,
        );

        return $flags;
    }
}
