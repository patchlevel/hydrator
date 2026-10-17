<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Middleware;

use ArrayObject;
use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Mapper\ReflectionMapper;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\MiddlewareMapper;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Tests\Unit\Fixture\RecordingMiddleware;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MiddlewareMapper::class)]
final class MiddlewareMapperTest extends TestCase
{
    public function testMiddlewaresAreCalledInOrder(): void
    {
        $calls = self::calls();
        $mapper = new MiddlewareMapper(
            new RecordingMiddleware('first', $calls),
            self::metadata(),
            new MiddlewareMapper(new RecordingMiddleware('second', $calls), self::metadata(), self::mapper()),
        );

        $skill = $mapper->hydrate(['name' => 'php'], []);
        $data = $mapper->extract($skill, []);

        self::assertEquals(new Skill('php'), $skill);
        self::assertSame(['name' => 'php'], $data);
        self::assertSame(['hydrate first', 'hydrate second', 'extract first', 'extract second'], $calls->getArrayCopy());
    }

    public function testMiddlewareGetsTheMetadataAndTheNextMapper(): void
    {
        $metadata = self::metadata();
        $next = self::mapper();
        $skill = new Skill('php');

        $middleware = $this->createMock(Middleware::class);
        $middleware->expects($this->once())->method('hydrate')
            ->with($metadata, ['name' => 'php'], ['foo' => 'bar'], $next)
            ->willReturn($skill);
        $middleware->expects($this->once())->method('extract')
            ->with($metadata, $skill, ['foo' => 'bar'], $next)
            ->willReturn(['name' => 'php']);

        $mapper = new MiddlewareMapper($middleware, $metadata, $next);

        self::assertSame($skill, $mapper->hydrate(['name' => 'php'], ['foo' => 'bar']));
        self::assertSame(['name' => 'php'], $mapper->extract($skill, ['foo' => 'bar']));
    }

    public function testRestOfTheChainCanBeCalledTwice(): void
    {
        $calls = self::calls();
        $twice = new class implements Middleware {
            /**
             * @param ClassMetadata<T>     $metadata
             * @param array<string, mixed> $data
             * @param array<string, mixed> $context
             * @param Mapper<T>            $next
             *
             * @return T
             *
             * @template T of object
             */
            public function hydrate(ClassMetadata $metadata, array $data, array $context, Mapper $next): object
            {
                $next->hydrate($data, $context);

                return $next->hydrate($data, $context);
            }

            /**
             * @param ClassMetadata<T>     $metadata
             * @param T                    $object
             * @param array<string, mixed> $context
             * @param Mapper<T>            $next
             *
             * @return array<string, mixed>
             *
             * @template T of object
             */
            public function extract(ClassMetadata $metadata, object $object, array $context, Mapper $next): array
            {
                $next->extract($object, $context);

                return $next->extract($object, $context);
            }
        };

        $mapper = new MiddlewareMapper(
            $twice,
            self::metadata(),
            new MiddlewareMapper(new RecordingMiddleware('inner', $calls), self::metadata(), self::mapper()),
        );

        $skill = $mapper->hydrate(['name' => 'php'], []);
        $mapper->extract($skill, []);

        self::assertSame(['hydrate inner', 'hydrate inner', 'extract inner', 'extract inner'], $calls->getArrayCopy());
    }

    /** @param list<string> $expected */
    #[DataProvider('skipProvider')]
    public function testSkippedDirectionCallsTheNextMapperDirectly(Skip $skip, array $expected): void
    {
        $calls = self::calls();
        $mapper = new MiddlewareMapper(new RecordingMiddleware('middleware', $calls), self::metadata(), self::mapper(), $skip);

        $skill = $mapper->hydrate(['name' => 'php'], []);

        self::assertEquals(new Skill('php'), $skill);
        self::assertSame(['name' => 'php'], $mapper->extract($skill, []));
        self::assertSame($expected, $calls->getArrayCopy());
    }

    /** @return iterable<string, array{Skip, list<string>}> */
    public static function skipProvider(): iterable
    {
        yield 'none' => [Skip::None, ['hydrate middleware', 'extract middleware']];
        yield 'hydrate' => [Skip::Hydrate, ['extract middleware']];
        yield 'extract' => [Skip::Extract, ['hydrate middleware']];
        yield 'both' => [Skip::Both, []];
    }

    /** @return ReflectionMapper<Skill> */
    private static function mapper(): ReflectionMapper
    {
        return new ReflectionMapper(self::metadata());
    }

    /** @return ArrayObject<int, string> */
    private static function calls(): ArrayObject
    {
        return new ArrayObject();
    }

    /** @return ClassMetadata<Skill> */
    private static function metadata(): ClassMetadata
    {
        return (new AttributeMetadataFactory())->metadata(Skill::class);
    }
}
