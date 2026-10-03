<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMiddlewareExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMiddlewareNotWritable;
use Patchlevel\Hydrator\Middleware\TransformMiddleware;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function filemtime;
use function sprintf;
use function sys_get_temp_dir;
use function touch;
use function uniqid;

#[CoversClass(GeneratedMiddlewareExtension::class)]
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
        $hydrator = (new StackHydratorBuilder())->useExtension(new CoreExtension())->useExtension($extension)->build();

        self::assertFileExists($file);
        $mtime = filemtime($file);
        touch($file, $mtime - 100);

        // the class is already loaded, the file is neither regenerated nor required again
        (new StackHydratorBuilder())->useExtension(new CoreExtension())->useExtension($extension)->build();
        self::assertSame($mtime - 100, filemtime($file));

        $event = $hydrator->hydrate(ProfileCreated::class, ['profileId' => '1', 'email' => 'info@patchlevel.de']);
        self::assertEquals(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')), $event);
    }

    public function testMiddlewareStack(): void
    {
        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new GeneratedMiddlewareExtension(GeneratedStackHydratorTest::cachePath(), [ProfileCreated::class]))
            ->build();

        $middlewares = $hydrator->middlewares();

        self::assertCount(2, $middlewares);
        self::assertStringStartsWith(GeneratedMiddlewareExtension::NAMESPACE . '\\', $middlewares[0]::class);
        self::assertInstanceOf(TransformMiddleware::class, $middlewares[1]);
    }

    public function testNotWritable(): void
    {
        $this->expectException(GeneratedMiddlewareNotWritable::class);

        $file = sys_get_temp_dir() . '/' . uniqid('not-a-directory');
        touch($file);

        (new StackHydratorBuilder())
            ->useExtension(new GeneratedMiddlewareExtension($file, [ProfileCreated::class], className: 'NotWritable_' . uniqid()))
            ->build();
    }
}
