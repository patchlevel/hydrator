<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Upcast;

use Patchlevel\Hydrator\Extension\Upcast\Attribute\UpcasterFor;
use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;
use Patchlevel\Hydrator\Extension\Upcast\Upcaster;
use Patchlevel\Hydrator\Extension\Upcast\UpcastersNotInVersionOrder;
use Patchlevel\Hydrator\Extension\Upcast\UpcastExtension;
use Patchlevel\Hydrator\Extension\Upcast\UpcastMiddleware;
use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Tests\Unit\Extension\Upcast\Fixture\UpcastFixture;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function assert;
use function is_array;
use function sprintf;

#[CoversClass(UpcastMiddleware::class)]
#[CoversClass(UpcastersNotInVersionOrder::class)]
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

        /** @var Mapper<UpcastFixture>&MockObject $next */
        $next = $this->createMock(Mapper::class);
        $next->expects(self::once())
            ->method('hydrate')
            ->with(['firstName' => 'Jane', 'lastName' => 'Doe', 'name' => 'Jane Doe'], [])
            ->willReturn($expectedObject);

        $object = $middleware->hydrate($metadata, ['firstName' => 'Jane', 'lastName' => 'Doe'], [], $next);

        self::assertSame($expectedObject, $object);
    }

    public function testExtract(): void
    {
        $middleware = new UpcastMiddleware([]);
        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);
        $object = new UpcastFixture('Jane Doe');

        /** @var Mapper<UpcastFixture>&MockObject $next */
        $next = $this->createMock(Mapper::class);
        $next->expects(self::once())
            ->method('extract')
            ->with($object, [])
            ->willReturn(['name' => 'Jane Doe']);

        self::assertSame(['name' => 'Jane Doe'], $middleware->extract($metadata, $object, [], $next));
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

        /** @var Mapper<UpcastFixture>&MockObject $next */
        $next = $this->createMock(Mapper::class);
        $next->expects(self::once())
            ->method('hydrate')
            ->with(['name' => 'Jane Doe', 'matching' => true], [])
            ->willReturn($expectedObject);

        $object = $middleware->hydrate($metadata, ['name' => 'Jane Doe'], [], $next);

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

    /** @return iterable<string, array{array<string, mixed>, list<string>}> */
    public static function provideVersions(): iterable
    {
        yield 'version 1' => [[UpcastExtension::VERSIONS => [UpcastFixture::class => 1]], ['1 to 2', 'always', '2 to 3']];
        yield 'version 2' => [[UpcastExtension::VERSIONS => [UpcastFixture::class => 2]], ['always', '2 to 3']];
        yield 'current version' => [[UpcastExtension::VERSIONS => [UpcastFixture::class => 3]], ['always']];
        yield 'without version' => [[], ['always']];
        yield 'version of another class' => [[UpcastExtension::VERSIONS => [ProfileCreated::class => 1]], ['always']];
        yield 'invalid versions' => [[UpcastExtension::VERSIONS => 1], ['always']];
        yield 'invalid version' => [[UpcastExtension::VERSIONS => [UpcastFixture::class => '1']], ['always']];
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string>         $steps
     */
    #[DataProvider('provideVersions')]
    public function testHydrateVersioned(array $context, array $steps): void
    {
        $secondStep = new #[UpcasterFor(UpcastFixture::class, from: 2)]
        class implements Upcaster {
            /**
             * @param array<string, mixed> $data
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             */
            public function upcast(ClassMetadata $metadata, array $data, array $context): array
            {
                $steps = $data['steps'] ?? [];
                assert(is_array($steps));
                $steps[] = '2 to 3';
                $data['steps'] = $steps;

                return $data;
            }
        };

        $middleware = new UpcastMiddleware([
            self::step('1 to 2', 1),
            self::step('always'),
            $secondStep,
        ]);

        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);
        $expectedObject = new UpcastFixture('Jane');

        /** @var Mapper<UpcastFixture>&MockObject $next */
        $next = $this->createMock(Mapper::class);
        $next->expects(self::once())
            ->method('hydrate')
            ->with(['name' => 'Jane', 'steps' => $steps], $context)
            ->willReturn($expectedObject);

        self::assertSame($expectedObject, $middleware->hydrate($metadata, ['name' => 'Jane'], $context, $next));
    }

    public function testSkipExtractWithVersionedUpcasters(): void
    {
        $middleware = new UpcastMiddleware([
            CallbackUpcaster::forClass(UpcastFixture::class, static fn (array $data): array => $data, from: 1),
        ]);

        self::assertSame(
            Skip::Extract,
            $middleware->skip((new AttributeMetadataFactory())->metadata(UpcastFixture::class)),
        );
        self::assertSame(
            Skip::Both,
            $middleware->skip((new AttributeMetadataFactory())->metadata(ProfileCreated::class)),
        );
    }

    public function testUpcastersHaveToBeInVersionOrder(): void
    {
        $this->expectException(UpcastersNotInVersionOrder::class);
        $this->expectExceptionMessage(sprintf(
            'The upcasters of %s have to be registered in the order of their versions, the upcaster from version 1 comes after the one from version 2.',
            UpcastFixture::class,
        ));

        new UpcastMiddleware([
            CallbackUpcaster::forClass(UpcastFixture::class, static fn (array $data): array => $data, from: 2),
            CallbackUpcaster::forClass(UpcastFixture::class, static fn (array $data): array => $data),
            CallbackUpcaster::forClass(UpcastFixture::class, static fn (array $data): array => $data, from: 1),
        ]);
    }

    public function testUpcastersOfTheSameVersionOrOtherClasses(): void
    {
        $middleware = new UpcastMiddleware([
            CallbackUpcaster::forClass(UpcastFixture::class, static fn (array $data): array => $data, from: 2),
            CallbackUpcaster::forClass(ProfileCreated::class, static fn (array $data): array => $data, from: 1),
            CallbackUpcaster::forClass(UpcastFixture::class, static fn (array $data): array => $data, from: 2),
            CallbackUpcaster::forClass(UpcastFixture::class, static fn (array $data): array => $data, from: 3),
        ]);

        self::assertSame(
            Skip::Extract,
            $middleware->skip((new AttributeMetadataFactory())->metadata(UpcastFixture::class)),
        );
    }

    /** An upcaster which adds its name to the steps in the data. */
    private static function step(string $name, int|null $from = null): CallbackUpcaster
    {
        return CallbackUpcaster::forClass(
            UpcastFixture::class,
            static function (array $data) use ($name): array {
                $steps = $data['steps'] ?? [];
                assert(is_array($steps));
                $steps[] = $name;
                $data['steps'] = $steps;

                return $data;
            },
            $from,
        );
    }
}
