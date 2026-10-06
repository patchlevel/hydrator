<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Lifecycle;

use Patchlevel\Hydrator\Extension\Lifecycle\Lifecycle;
use Patchlevel\Hydrator\Extension\Lifecycle\LifecycleMiddleware;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Next;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Tests\Unit\Extension\Lifecycle\Fixture\LifecycleFixture;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

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

        $innerMiddleware = new class implements Middleware {
            /**
             * @param ClassMetadata<T>     $metadata
             * @param array<string, mixed> $data
             * @param array<string, mixed> $context
             *
             * @return T
             *
             * @template T of object
             */
            public function hydrate(ClassMetadata $metadata, array $data, array $context, Next $next): object
            {
                $name = $data['name'] ?? '';
                assert(is_string($name));

                $object = new LifecycleFixture($name);

                assert($object instanceof $metadata->className);

                return $object;
            }

            /**
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             */
            public function extract(ClassMetadata $metadata, object $object, array $context, Next $next): array
            {
                return [];
            }
        };

        $next = new Next([$innerMiddleware], self::createStub(ClassTransformer::class));

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

        $innerMiddleware = new class implements Middleware {
            /**
             * @param ClassMetadata<T>     $metadata
             * @param array<string, mixed> $data
             * @param array<string, mixed> $context
             *
             * @return T
             *
             * @template T of object
             */
            public function hydrate(ClassMetadata $metadata, array $data, array $context, Next $next): object
            {
                $object = new stdClass();

                assert($object instanceof $metadata->className);

                return $object;
            }

            /**
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             */
            public function extract(ClassMetadata $metadata, object $object, array $context, Next $next): array
            {
                if ($object instanceof LifecycleFixture) {
                    return ['name' => $object->name];
                }

                return [];
            }
        };

        $next = new Next([$innerMiddleware], self::createStub(ClassTransformer::class));
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
