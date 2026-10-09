<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use DateTimeInterface;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\DecoratorsNotApplied;
use Patchlevel\Hydrator\Extension\Generated\GeneratedHydrator;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMiddlewareExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMiddlewareNotLoaded;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMiddlewareNotWritable;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMiddlewareSlot;
use Patchlevel\Hydrator\Extension\Tracing\TracingExtension;
use Patchlevel\Hydrator\Middleware\TransformMiddleware;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Extension\Tracing\Fixture\RecordingTracer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferredDateDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\LifecycleFixture;
use Patchlevel\Hydrator\Tests\Unit\Fixture\NestedLifecycleDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function clearstatcache;
use function filemtime;
use function sprintf;
use function sys_get_temp_dir;
use function touch;
use function uniqid;

#[CoversClass(GeneratedMiddlewareExtension::class)]
#[CoversClass(GeneratedMiddlewareSlot::class)]
#[CoversClass(GeneratedHydrator::class)]
final class GeneratedMiddlewareExtensionTest extends TestCase
{
    public function testClassNameDependsOnConfiguration(): void
    {
        $path = GeneratedStackHydratorTest::cachePath();

        $a = new GeneratedMiddlewareExtension($path, [ProfileCreated::class]);
        $b = new GeneratedMiddlewareExtension($path, [ProfileCreated::class, Skill::class]);

        self::assertSame($a->defaultClassName(), (new GeneratedMiddlewareExtension($path, [ProfileCreated::class]))->defaultClassName());
        self::assertNotSame($a->defaultClassName(), $b->defaultClassName());
    }

    public function testGeneratedFileIsCachedAndReused(): void
    {
        $path = GeneratedStackHydratorTest::cachePath() . '/' . uniqid('cache', true);
        $className = 'Cached_' . uniqid();
        $file = sprintf('%s/%s.php', $path, $className);

        $extension = new GeneratedMiddlewareExtension($path, [ProfileCreated::class], className: $className);
        $hydrator = (new StackHydratorBuilder())->useExtension(new CoreExtension())->useExtension($extension)->buildHydrator();

        self::assertFileExists($file);
        $mtime = filemtime($file);
        touch($file, $mtime - 100);

        // the class is already loaded, the file is neither regenerated nor required again
        (new StackHydratorBuilder())->useExtension(new CoreExtension())->useExtension($extension)->buildHydrator();
        clearstatcache(true, $file);
        self::assertSame($mtime - 100, filemtime($file));

        $event = $hydrator->hydrate(ProfileCreated::class, ['profileId' => '1', 'email' => 'info@patchlevel.de']);
        self::assertEquals(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')), $event);
    }

    public function testMiddlewareStack(): void
    {
        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new GeneratedMiddlewareExtension(GeneratedStackHydratorTest::cachePath(), [ProfileCreated::class]))
            ->buildHydrator();

        self::assertInstanceOf(GeneratedHydrator::class, $hydrator);
        self::assertStringStartsWith(GeneratedMiddlewareExtension::NAMESPACE . '\\', $hydrator->middleware()::class);
        self::assertSame($hydrator, $hydrator->stack()->rootHydrator());

        $middlewares = $hydrator->stack()->middlewares();

        self::assertCount(2, $middlewares);
        self::assertInstanceOf(GeneratedMiddlewareSlot::class, $middlewares[0]);
        self::assertTrue($middlewares[0]->holds($hydrator->middleware()));
        self::assertInstanceOf(TransformMiddleware::class, $middlewares[1]);
    }

    public function testBuildIsNotSupported(): void
    {
        $this->expectException(DecoratorsNotApplied::class);

        (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new GeneratedMiddlewareExtension(GeneratedStackHydratorTest::cachePath(), [ProfileCreated::class]))
            ->build();
    }

    public function testSlotWithoutGeneratedMiddleware(): void
    {
        $this->expectException(GeneratedMiddlewareNotLoaded::class);

        (new GeneratedMiddlewareSlot())->skip((new StackHydrator())->metadata(ProfileCreated::class));
    }

    public function testTracingWrapsTheGeneratedHydrator(): void
    {
        $tracer = new RecordingTracer();

        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new GeneratedMiddlewareExtension(GeneratedStackHydratorTest::cachePath(), [NestedLifecycleDto::class]))
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
        // when the extension is configured. The code has to be generated when the hydrator is built.
        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new GeneratedMiddlewareExtension(GeneratedStackHydratorTest::cachePath(), [InferredDateDto::class], debug: true))
            ->useExtension(new CoreExtension())
            ->buildHydrator();

        $data = ['createdAt' => '2024-05-04T10:15:30+00:00'];
        $object = $hydrator->hydrate(InferredDateDto::class, $data);

        self::assertSame('2024-05-04T10:15:30+00:00', $object->createdAt->format(DateTimeInterface::ATOM));
        self::assertSame($data, $hydrator->extract($object));
    }

    public function testNotWritable(): void
    {
        $this->expectException(GeneratedMiddlewareNotWritable::class);

        $file = sys_get_temp_dir() . '/' . uniqid('not-a-directory');
        touch($file);

        (new StackHydratorBuilder())
            ->useExtension(new GeneratedMiddlewareExtension($file, [ProfileCreated::class], className: 'NotWritable_' . uniqid()))
            ->buildHydrator();
    }
}
