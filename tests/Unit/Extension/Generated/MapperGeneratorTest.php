<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use Patchlevel\Hydrator\Extension\Generated\ClassFingerprint;
use Patchlevel\Hydrator\Extension\Generated\ClassNotGeneratable;
use Patchlevel\Hydrator\Extension\Generated\ClassPlan;
use Patchlevel\Hydrator\Extension\Generated\ClassPlanner;
use Patchlevel\Hydrator\Extension\Generated\CodeEmitter;
use Patchlevel\Hydrator\Extension\Generated\GeneratedCode;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapper;
use Patchlevel\Hydrator\Extension\Generated\Literal;
use Patchlevel\Hydrator\Extension\Generated\MapperGenerator;
use Patchlevel\Hydrator\Extension\Generated\NestedPlan;
use Patchlevel\Hydrator\Extension\Generated\OutdatedGeneratedMapper;
use Patchlevel\Hydrator\Extension\Generated\PropertyPlan;
use Patchlevel\Hydrator\Extension\Generated\Templates;
use Patchlevel\Hydrator\Extension\Generated\ValueKind;
use Patchlevel\Hydrator\Mapper\CallStack;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\Tests\Unit\Fixture\FingerprintChangedDefaultDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\FingerprintChangedVisibilityDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\FingerprintDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\LifecycleFixture;
use Patchlevel\Hydrator\Tests\Unit\Fixture\MultilineDefaultDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\NestedLifecycleDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;

use function assert;
use function file_put_contents;
use function getenv;
use function is_a;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(MapperGenerator::class)]
#[CoversClass(GeneratedCode::class)]
#[CoversClass(GeneratedMapper::class)]
#[CoversClass(ClassPlanner::class)]
#[CoversClass(ClassFingerprint::class)]
#[CoversClass(Literal::class)]
#[CoversClass(ClassPlan::class)]
#[CoversClass(PropertyPlan::class)]
#[CoversClass(NestedPlan::class)]
#[CoversClass(ValueKind::class)]
#[CoversClass(CodeEmitter::class)]
#[CoversClass(Templates::class)]
final class MapperGeneratorTest extends TestCase
{
    private static int $counter = 0;

    public function testGenerateIsDeterministic(): void
    {
        $metadata = (new AttributeMetadataFactory())->metadata(ProfileCreated::class);

        $first = (new MapperGenerator())->generate($metadata, 'Foo\\Bar');
        $second = (new MapperGenerator())->generate($metadata, 'Foo\\Bar');

        self::assertSame($first->code, $second->code);
        self::assertStringContainsString('namespace Foo;', $first->code);
        self::assertStringContainsString('final class Bar extends GeneratedMapper', $first->code);
        self::assertStringNotContainsString('strict_types=1', $first->code);
    }

    public function testNestedClasses(): void
    {
        $code = (new MapperGenerator())->generate(
            (new AttributeMetadataFactory())->metadata(NestedLifecycleDto::class),
            'Foo\\Bar',
        );

        self::assertSame([LifecycleFixture::class], $code->nested);
    }

    /**
     * The snapshots make every change of the generated code visible in the diff of a pull request.
     * Run "make snapshot" after an intended change of the generator.
     */
    #[RequiresPhp('>=8.5')]
    public function testGeneratedCodeMatchesSnapshot(): void
    {
        $metadataFactory = new AttributeMetadataFactory();

        foreach (GeneratedStackHydratorTest::CLASSES as $class) {
            $metadata = $metadataFactory->metadata($class);
            $className = $metadata->reflection->getShortName() . 'Mapper';
            $file = __DIR__ . '/Snapshot/' . $className . '.php';

            $code = (new MapperGenerator())->generate($metadata, 'Patchlevel\\Hydrator\\Tests\\Snapshot\\' . $className)->code;

            if (getenv('UPDATE_SNAPSHOTS') === '1') {
                file_put_contents($file, $code);
            }

            self::assertStringEqualsFile($file, $code, 'The generated code changed, run "make snapshot" if the change is intended.');
        }
    }

    public function testDefaultsWithControlCharactersAreInlinedUnchanged(): void
    {
        $mapper = $this->load((new AttributeMetadataFactory())->metadata(MultilineDefaultDto::class));

        $object = $mapper->hydrate([], []);

        self::assertInstanceOf(MultilineDefaultDto::class, $object);
        self::assertSame("foo bar\nbaz", $object->name);
        self::assertSame("first\nsecond", $object->text);
        self::assertSame(["a\tb" => "c\nd \$e \"f\""], $object->lines);
    }

    public function testFailedInitializationIsRetried(): void
    {
        $metadata = (new AttributeMetadataFactory())->metadata(ProfileCreated::class);
        $mapper = $this->load($metadata);
        $data = ['profileId' => '1', 'email' => 'info@patchlevel.de'];

        // the metadata does not match the generated code anymore
        $property = $metadata->properties['email'];
        $normalizer = $property->normalizer;
        $property->normalizer = null;

        try {
            $mapper->hydrate($data, []);
            self::fail('the first call must fail');
        } catch (OutdatedGeneratedMapper) {
        }

        $property->normalizer = $normalizer;

        // the failed initialization must not leave the mapper half initialized
        $object = $mapper->hydrate($data, []);

        self::assertInstanceOf(ProfileCreated::class, $object);
        self::assertSame('info@patchlevel.de', $object->email->toString());
    }

    /** @param class-string $changed a class with the same properties as FingerprintDto but one changed detail */
    #[DataProvider('changedClasses')]
    public function testChangedClassChangesTheFingerprint(string $changed): void
    {
        $metadataFactory = new AttributeMetadataFactory();

        self::assertNotSame(
            ClassFingerprint::of($metadataFactory->metadata(FingerprintDto::class)),
            ClassFingerprint::of($metadataFactory->metadata($changed)),
        );
    }

    /** @return iterable<string, array{class-string}> */
    public static function changedClasses(): iterable
    {
        yield 'changed default' => [FingerprintChangedDefaultDto::class];
        yield 'changed visibility' => [FingerprintChangedVisibilityDto::class];
    }

    public function testClassNormalizerIsNotGeneratable(): void
    {
        $this->expectException(ClassNotGeneratable::class);

        (new MapperGenerator())->generate(
            (new AttributeMetadataFactory())->metadata(ProfileId::class),
            'Foo\\Bar',
        );
    }

    public function testAnonymousClassIsNotGeneratable(): void
    {
        $object = new class {
            public string $name = 'foo';
        };

        $this->expectException(ClassNotGeneratable::class);

        (new MapperGenerator())->generate((new AttributeMetadataFactory())->metadata($object::class), 'Foo\\Bar');
    }

    /**
     * @param ClassMetadata<T> $metadata
     *
     * @template T of object
     */
    private function load(ClassMetadata $metadata, StackHydrator|null $hydrator = null): GeneratedMapper
    {
        $fqcn = 'Patchlevel\\Hydrator\\Tests\\Generated\\Mapper' . self::$counter++;
        $code = (new MapperGenerator())->generate($metadata, $fqcn)->code;

        $file = tempnam(sys_get_temp_dir(), 'mapper');
        assert($file !== false);
        file_put_contents($file, $code);

        require $file;
        unlink($file);

        assert(is_a($fqcn, GeneratedMapper::class, true));

        return new $fqcn($metadata, $hydrator ?? new StackHydrator(), new CallStack());
    }
}
