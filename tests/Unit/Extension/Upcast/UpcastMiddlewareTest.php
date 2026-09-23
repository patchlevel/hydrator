<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Upcast;

use Patchlevel\Hydrator\Extension\Upcast\Attribute\UpcasterFor;
use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;
use Patchlevel\Hydrator\Extension\Upcast\Upcaster;
use Patchlevel\Hydrator\Extension\Upcast\UpcastMiddleware;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Tests\Unit\Extension\Upcast\Fixture\UpcastFixture;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpcastMiddleware::class)]
final class UpcastMiddlewareTest extends TestCase
{
    public function testHydrate(): void
    {
        $middleware = new UpcastMiddleware([
            CallbackUpcaster::forClass(
                UpcastFixture::class,
                /**
                 * @param array{name: string}|array{firstName: string, lastName: string} $data
                 *
                 * @return array{name: string}
                 */
                static function (array $data): array {
                    if (isset($data['name'])) {
                        return $data;
                    }

                    $data['name'] = $data['firstName'] . ' ' . $data['lastName'];

                    return $data;
                },
            ),
        ]);

        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);

        $expectedObject = new UpcastFixture('Jane Doe');

        $nextMiddleware = $this->createMock(Middleware::class);
        $nextMiddleware->expects(self::once())
            ->method('hydrate')
            ->with($metadata, ['firstName' => 'Jane', 'lastName' => 'Doe', 'name' => 'Jane Doe'], [], self::isInstanceOf(Stack::class))
            ->willReturn($expectedObject);

        $stack = new Stack([$nextMiddleware]);

        $object = $middleware->hydrate($metadata, ['firstName' => 'Jane', 'lastName' => 'Doe'], [], $stack);

        self::assertSame($expectedObject, $object);
    }

    public function testExtract(): void
    {
        $middleware = new UpcastMiddleware([]);
        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);
        $object = new UpcastFixture('Jane Doe');

        $nextMiddleware = $this->createMock(Middleware::class);
        $nextMiddleware->expects(self::once())
            ->method('extract')
            ->with($metadata, $object, [], self::isInstanceOf(Stack::class))
            ->willReturn(['name' => 'Jane Doe']);

        $stack = new Stack([$nextMiddleware]);

        self::assertSame(['name' => 'Jane Doe'], $middleware->extract($metadata, $object, [], $stack));
    }

    public function testSkipWithoutUpcasters(): void
    {
        $middleware = new UpcastMiddleware([]);
        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);

        self::assertSame(Skip::Both, $middleware->skip($metadata));
    }

    public function testSkipExtractWithUpcasters(): void
    {
        $middleware = new UpcastMiddleware([
            CallbackUpcaster::forClass(
                UpcastFixture::class,
                static fn (array $data): array => $data,
            ),
        ]);
        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);

        self::assertSame(Skip::Extract, $middleware->skip($metadata));
    }

    public function testSkipBothWhenNoCallbackUpcasterTargetsTheClass(): void
    {
        $middleware = new UpcastMiddleware([
            CallbackUpcaster::forClass(
                ProfileCreated::class,
                static fn (array $data): array => $data,
            ),
        ]);
        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);

        self::assertSame(Skip::Both, $middleware->skip($metadata));
    }

    public function testSkipExtractWhenAnyCallbackUpcasterTargetsTheClass(): void
    {
        $middleware = new UpcastMiddleware([
            CallbackUpcaster::forClass(
                ProfileCreated::class,
                static fn (array $data): array => $data,
            ),
            CallbackUpcaster::forClass(
                UpcastFixture::class,
                static fn (array $data): array => $data,
            ),
        ]);
        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);

        self::assertSame(Skip::Extract, $middleware->skip($metadata));
    }

    public function testHydrateOnlyCallsUpcastersTargetingTheClass(): void
    {
        $matching = new #[UpcasterFor(UpcastFixture::class)]
        class implements Upcaster {
            /**
             * @param ClassMetadata<T>     $metadata
             * @param array<string, mixed> $data
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             *
             * @template T of object
             */
            public function upcast(ClassMetadata $metadata, array $data, array $context): array
            {
                $data['matching'] = true;

                return $data;
            }
        };

        $notMatching = new #[UpcasterFor(ProfileCreated::class)]
        class implements Upcaster {
            /**
             * @param ClassMetadata<T>     $metadata
             * @param array<string, mixed> $data
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             *
             * @template T of object
             */
            public function upcast(ClassMetadata $metadata, array $data, array $context): array
            {
                $data['notMatching'] = true;

                return $data;
            }
        };

        $middleware = new UpcastMiddleware([$notMatching, $matching]);
        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);

        $expectedObject = new UpcastFixture('Jane Doe');

        $nextMiddleware = $this->createMock(Middleware::class);
        $nextMiddleware->expects(self::once())
            ->method('hydrate')
            ->with($metadata, ['name' => 'Jane Doe', 'matching' => true], [], self::isInstanceOf(Stack::class))
            ->willReturn($expectedObject);

        $stack = new Stack([$nextMiddleware]);

        $object = $middleware->hydrate($metadata, ['name' => 'Jane Doe'], [], $stack);

        self::assertSame($expectedObject, $object);
    }

    public function testSkipBothWhenNoAttributeUpcasterTargetsTheClass(): void
    {
        $middleware = new UpcastMiddleware([
            new #[UpcasterFor(ProfileCreated::class)]
            class implements Upcaster {
                /**
                 * @param ClassMetadata<T>     $metadata
                 * @param array<string, mixed> $data
                 * @param array<string, mixed> $context
                 *
                 * @return array<string, mixed>
                 *
                 * @template T of object
                 */
                public function upcast(ClassMetadata $metadata, array $data, array $context): array
                {
                    return $data;
                }
            },
        ]);
        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);

        self::assertSame(Skip::Both, $middleware->skip($metadata));
    }

    public function testSkipExtractWhenAttributeUpcasterTargetsTheClass(): void
    {
        $middleware = new UpcastMiddleware([
            new #[UpcasterFor(UpcastFixture::class)]
            class implements Upcaster {
                /**
                 * @param ClassMetadata<T>     $metadata
                 * @param array<string, mixed> $data
                 * @param array<string, mixed> $context
                 *
                 * @return array<string, mixed>
                 *
                 * @template T of object
                 */
                public function upcast(ClassMetadata $metadata, array $data, array $context): array
                {
                    return $data;
                }
            },
        ]);
        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);

        self::assertSame(Skip::Extract, $middleware->skip($metadata));
    }

    public function testSkipExtractWithUnknownUpcaster(): void
    {
        $middleware = new UpcastMiddleware([
            new class implements Upcaster {
                /**
                 * @param ClassMetadata<T>     $metadata
                 * @param array<string, mixed> $data
                 * @param array<string, mixed> $context
                 *
                 * @return array<string, mixed>
                 *
                 * @template T of object
                 */
                public function upcast(ClassMetadata $metadata, array $data, array $context): array
                {
                    return $data;
                }
            },
        ]);
        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);

        self::assertSame(Skip::Extract, $middleware->skip($metadata));
    }
}
