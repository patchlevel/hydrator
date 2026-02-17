<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use Patchlevel\Hydrator\Extension\Generated\ClassFingerprint;
use Patchlevel\Hydrator\Extension\Generated\ClassNotGeneratable;
use Patchlevel\Hydrator\Extension\Generated\ClassPlan;
use Patchlevel\Hydrator\Extension\Generated\ClassPlanner;
use Patchlevel\Hydrator\Extension\Generated\CodeEmitter;
use Patchlevel\Hydrator\Extension\Generated\HydratorNotSet;
use Patchlevel\Hydrator\Extension\Generated\Literal;
use Patchlevel\Hydrator\Extension\Generated\MiddlewareGenerator;
use Patchlevel\Hydrator\Extension\Generated\OutdatedGeneratedMiddleware;
use Patchlevel\Hydrator\Extension\Generated\PropertyPlan;
use Patchlevel\Hydrator\Extension\Generated\Templates;
use Patchlevel\Hydrator\Extension\Generated\ValueKind;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ChildDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\FingerprintChangedDefaultDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\FingerprintChangedVisibilityDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\FingerprintDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\MultilineDefaultDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;

use function assert;
use function file_put_contents;
use function getenv;
use function is_a;
use function sprintf;
use function str_replace;
use function tempnam;
use function unlink;

#[CoversClass(MiddlewareGenerator::class)]
#[CoversClass(ClassPlanner::class)]
#[CoversClass(ClassFingerprint::class)]
#[CoversClass(Literal::class)]
#[CoversClass(ClassPlan::class)]
#[CoversClass(PropertyPlan::class)]
#[CoversClass(ValueKind::class)]
#[CoversClass(CodeEmitter::class)]
#[CoversClass(Templates::class)]
final class MiddlewareGeneratorTest extends TestCase
{
    private static int $counter = 0;

    public function testDumpIsDeterministic(): void
    {
        $generator = new MiddlewareGenerator(new AttributeMetadataFactory());

        $first = $generator->dump([ProfileCreated::class, Skill::class], 'Foo\\Bar');
        $second = $generator->dump([ProfileCreated::class, Skill::class], 'Foo\\Bar');

        self::assertSame($first, $second);
        self::assertStringContainsString('namespace Foo;', $first);
        self::assertStringContainsString('final class Bar implements SkippableMiddleware, HydratorAwareMiddleware', $first);
        self::assertStringNotContainsString('strict_types=1', $first);
    }

    /**
     * The snapshot makes every change of the generated code visible in the diff of a pull request.
     * Run "make snapshot" after an intended change of the generator.
     */
    #[RequiresPhp('>=8.5')]
    public function testGeneratedCodeMatchesSnapshot(): void
    {
        $file = __DIR__ . '/Snapshot/SnapshotMiddleware.php';
        $code = (new MiddlewareGenerator(new AttributeMetadataFactory()))->dump(
            GeneratedStackHydratorTest::CLASSES,
            'Patchlevel\\Hydrator\\Tests\\Snapshot\\SnapshotMiddleware',
        );

        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            file_put_contents($file, $code);
        }

        self::assertStringEqualsFile($file, $code, 'The generated code changed, run "make snapshot" if the change is intended.');
    }

    public function testClassesWithClassNormalizerAreSkipped(): void
    {
        $generator = new MiddlewareGenerator(new AttributeMetadataFactory());

        $code = $generator->dump([ProfileId::class], 'Foo\\Bar');

        self::assertStringNotContainsString(sprintf('\\%s::class', ProfileId::class), $code);
    }

    public function testAbstractClassIsNotGeneratable(): void
    {
        $this->expectException(ClassNotGeneratable::class);

        (new MiddlewareGenerator(new AttributeMetadataFactory()))->dump([ChildDto::class], 'Foo\\Bar');
    }

    public function testUnknownClassIsNotGeneratable(): void
    {
        $this->expectException(ClassNotGeneratable::class);

        /** @phpstan-ignore argument.type */
        (new MiddlewareGenerator(new AttributeMetadataFactory()))->dump(['Unknown'], 'Foo\\Bar');
    }

    public function testMiddlewareRequiresHydrator(): void
    {
        $middleware = $this->load([ProfileCreated::class]);
        $metadata = (new AttributeMetadataFactory())->metadata(ProfileCreated::class);

        $this->expectException(HydratorNotSet::class);

        $middleware->hydrate($metadata, ['profileId' => '1', 'email' => 'info@patchlevel.de'], [], new Stack([$middleware]));
    }

    public function testFailedInitializationIsRetried(): void
    {
        $middleware = $this->load([ProfileCreated::class]);
        $metadata = (new AttributeMetadataFactory())->metadata(ProfileCreated::class);
        $data = ['profileId' => '1', 'email' => 'info@patchlevel.de'];

        try {
            $middleware->hydrate($metadata, $data, [], new Stack([$middleware]));
            self::fail('the first call must fail, the hydrator is not set yet');
        } catch (HydratorNotSet) {
        }

        // the failed initialization must not leave the class half initialized
        $hydrator = (new StackHydratorBuilder())->addMiddleware($middleware)->build();
        $object = $hydrator->hydrate(ProfileCreated::class, $data);

        self::assertSame('info@patchlevel.de', $object->email->toString());
    }

    public function testDefaultsWithControlCharactersAreInlinedUnchanged(): void
    {
        $middleware = $this->load([MultilineDefaultDto::class]);
        $hydrator = (new StackHydratorBuilder())->addMiddleware($middleware)->build();

        $object = $hydrator->hydrate(MultilineDefaultDto::class, []);

        self::assertSame("foo bar\nbaz", $object->name);
        self::assertSame("first\nsecond", $object->text);
        self::assertSame(["a\tb" => "c\nd \$e \"f\""], $object->lines);
    }

    /** @param class-string $changed a class with the same properties as FingerprintDto but one changed detail */
    #[DataProvider('changedClasses')]
    public function testChangedClassIsDetected(string $changed): void
    {
        // generated for FingerprintDto, then pointed at the changed class, like a class changing after generation
        $middleware = $this->load(
            [FingerprintDto::class],
            static fn (string $code): string => str_replace(FingerprintDto::class, $changed, $code),
        );
        $hydrator = (new StackHydratorBuilder())->addMiddleware($middleware)->build();

        $this->expectException(OutdatedGeneratedMiddleware::class);

        $hydrator->hydrate($changed, ['name' => 'foo']);
    }

    /** @return iterable<string, array{class-string}> */
    public static function changedClasses(): iterable
    {
        yield 'changed default' => [FingerprintChangedDefaultDto::class];
        yield 'changed visibility' => [FingerprintChangedVisibilityDto::class];
    }

    public function testOutdatedMiddlewareIsDetected(): void
    {
        $middleware = $this->load([ProfileCreated::class]);

        $hydrator = (new StackHydratorBuilder())
            ->addMiddleware($middleware)
            ->addMetadataEnricher(new class implements MetadataEnricher {
                public function enrich(ClassMetadata $classMetadata): void
                {
                    foreach ($classMetadata->properties as $property) {
                        $property->fieldName = 'renamed_' . $property->fieldName;
                    }
                }
            })
            ->build();

        $this->expectException(OutdatedGeneratedMiddleware::class);

        $hydrator->hydrate(ProfileCreated::class, ['renamed_profileId' => '1', 'renamed_email' => 'info@patchlevel.de']);
    }

    /**
     * @param list<class-string>              $classes
     * @param (callable(string): string)|null $transform applied to the generated code before it is loaded
     */
    private function load(array $classes, callable|null $transform = null): Middleware
    {
        $fqcn = 'Patchlevel\\Hydrator\\Tests\\Generated\\Middleware' . self::$counter++;
        $code = (new MiddlewareGenerator(new AttributeMetadataFactory()))->dump($classes, $fqcn);

        if ($transform !== null) {
            $code = $transform($code);
        }

        $file = tempnam(GeneratedStackHydratorTest::cachePath(), 'middleware');
        assert($file !== false);
        file_put_contents($file, $code);

        require $file;
        unlink($file);

        assert(is_a($fqcn, Middleware::class, true));

        return new $fqcn();
    }
}
