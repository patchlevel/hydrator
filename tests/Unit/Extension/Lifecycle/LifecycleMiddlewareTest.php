<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Lifecycle;

use Patchlevel\Hydrator\Extension\Lifecycle\Lifecycle;
use Patchlevel\Hydrator\Extension\Lifecycle\LifecycleMiddleware;
use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Tests\Unit\Extension\Lifecycle\Fixture\LifecycleFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;
use function is_string;

#[CoversClass(LifecycleMiddleware::class)]
final class LifecycleMiddlewareTest extends TestCase
{
    public function testHydrate(): void
    {
        $middleware = new LifecycleMiddleware();
        $metadata = $this->metadata(LifecycleFixture::class);
        $metadata->extras[Lifecycle::class] = new Lifecycle(
            preHydrate: 'preHydrate',
            postHydrate: 'postHydrate',
        );

        $next = $this->createMock(Mapper::class);
        $next
            ->expects($this->once())
            ->method('hydrate')
            ->willReturnCallback(static function (array $data): LifecycleFixture {
                $name = $data['name'] ?? '';
                assert(is_string($name));

                return new LifecycleFixture($name);
            });

        $object = $middleware->hydrate($metadata, ['name' => 'foo'], [], $next);

        self::assertInstanceOf(LifecycleFixture::class, $object);
        self::assertSame('foo [preHydrate] [postHydrate]', $object->name);
    }

    public function testExtract(): void
    {
        $middleware = new LifecycleMiddleware();
        $metadata = $this->metadata(LifecycleFixture::class);
        $metadata->extras[Lifecycle::class] = new Lifecycle(
            preExtract: 'preExtract',
            postExtract: 'postExtract',
        );

        $next = $this->createMock(Mapper::class);
        $next
            ->expects($this->once())
            ->method('extract')
            ->willReturnCallback(static function (object $object): array {
                assert($object instanceof LifecycleFixture);

                return ['name' => $object->name];
            });
        $object = new LifecycleFixture('foo');

        $data = $middleware->extract($metadata, $object, [], $next);

        self::assertSame('foo [preExtract] [postExtract]', $data['name']);
    }

    public function testSkipWithoutLifecycle(): void
    {
        $middleware = new LifecycleMiddleware();
        $metadata = $this->metadata(LifecycleFixture::class);

        self::assertSame(Skip::Both, $middleware->skip($metadata));
    }

    public function testSkipWithEmptyLifecycle(): void
    {
        $middleware = new LifecycleMiddleware();
        $metadata = $this->metadata(LifecycleFixture::class);
        $metadata->extras[Lifecycle::class] = new Lifecycle();

        self::assertSame(Skip::Both, $middleware->skip($metadata));
    }

    public function testSkipExtractWithOnlyHydrateHooks(): void
    {
        $middleware = new LifecycleMiddleware();
        $metadata = $this->metadata(LifecycleFixture::class);
        $metadata->extras[Lifecycle::class] = new Lifecycle(preHydrate: 'preHydrate');

        self::assertSame(Skip::Extract, $middleware->skip($metadata));
    }

    public function testSkipHydrateWithOnlyExtractHooks(): void
    {
        $middleware = new LifecycleMiddleware();
        $metadata = $this->metadata(LifecycleFixture::class);
        $metadata->extras[Lifecycle::class] = new Lifecycle(postExtract: 'postExtract');

        self::assertSame(Skip::Hydrate, $middleware->skip($metadata));
    }

    public function testSkipNothingWithHooksForBothDirections(): void
    {
        $middleware = new LifecycleMiddleware();
        $metadata = $this->metadata(LifecycleFixture::class);
        $metadata->extras[Lifecycle::class] = new Lifecycle(
            preHydrate: 'preHydrate',
            preExtract: 'preExtract',
        );

        self::assertSame(Skip::None, $middleware->skip($metadata));
    }

    /** @param class-string $class */
    private function metadata(string $class): ClassMetadata
    {
        return (new AttributeMetadataFactory())->metadata($class);
    }
}
