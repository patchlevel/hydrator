<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use Patchlevel\Hydrator\Extension\Generated\ClassNotGeneratable;
use Patchlevel\Hydrator\Extension\Generated\ClassPlan;
use Patchlevel\Hydrator\Extension\Generated\ClassPlanner;
use Patchlevel\Hydrator\Extension\Generated\CodeEmitter;
use Patchlevel\Hydrator\Extension\Generated\HydratorNotSet;
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
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;
use function file_put_contents;
use function getenv;
use function is_a;
use function sprintf;
use function tempnam;
use function unlink;

#[CoversClass(MiddlewareGenerator::class)]
#[CoversClass(ClassPlanner::class)]
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

    /** @param list<class-string> $classes */
    private function load(array $classes): Middleware
    {
        $fqcn = 'Patchlevel\\Hydrator\\Tests\\Generated\\Middleware' . self::$counter++;
        $code = (new MiddlewareGenerator(new AttributeMetadataFactory()))->dump($classes, $fqcn);

        $file = tempnam(GeneratedStackHydratorTest::cachePath(), 'middleware');
        assert($file !== false);
        file_put_contents($file, $code);

        require $file;
        unlink($file);

        assert(is_a($fqcn, Middleware::class, true));

        return new $fqcn();
    }
}
