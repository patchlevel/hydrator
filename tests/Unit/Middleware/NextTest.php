<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Middleware;

use ArrayObject;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Next;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use Patchlevel\Hydrator\Transformer\ReflectionTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Next::class)]
final class NextTest extends TestCase
{
    public function testMiddlewaresAreCalledInOrder(): void
    {
        $calls = self::calls();
        $next = new Next(
            [
                self::recording('first', $calls),
                self::recording('second', $calls),
            ],
            self::transformer(),
        );

        $skill = $next->hydrate(self::metadata(), ['name' => 'php'], []);
        $data = $next->extract(self::metadata(), $skill, []);

        self::assertEquals(new Skill('php'), $skill);
        self::assertSame(['name' => 'php'], $data);
        self::assertSame(['hydrate first', 'hydrate second', 'extract first', 'extract second'], $calls->getArrayCopy());
    }

    public function testRestOfTheStackCanBeCalledTwice(): void
    {
        $calls = self::calls();
        $twice = new class implements Middleware {
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
                $next->hydrate($metadata, $data, $context);

                return $next->hydrate($metadata, $data, $context);
            }

            /**
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             */
            public function extract(ClassMetadata $metadata, object $object, array $context, Next $next): array
            {
                $next->extract($metadata, $object, $context);

                return $next->extract($metadata, $object, $context);
            }
        };

        $next = new Next([$twice, self::recording('inner', $calls)], self::transformer());

        $skill = $next->hydrate(self::metadata(), ['name' => 'php'], []);
        $next->extract(self::metadata(), $skill, []);

        self::assertSame(['hydrate inner', 'hydrate inner', 'extract inner', 'extract inner'], $calls->getArrayCopy());
    }

    public function testPositionIsRestoredAfterAnException(): void
    {
        $calls = self::calls();
        $failing = new class implements Middleware {
            public bool $fail = true;

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
                if ($this->fail) {
                    throw new RuntimeException('failed');
                }

                return $next->hydrate($metadata, $data, $context);
            }

            /**
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             */
            public function extract(ClassMetadata $metadata, object $object, array $context, Next $next): array
            {
                if ($this->fail) {
                    throw new RuntimeException('failed');
                }

                return $next->extract($metadata, $object, $context);
            }
        };

        $next = new Next([self::recording('outer', $calls), $failing], self::transformer());

        try {
            $next->hydrate(self::metadata(), ['name' => 'php'], []);
            self::fail('the middleware has to fail');
        } catch (RuntimeException) {
        }

        try {
            $next->extract(self::metadata(), new Skill('php'), []);
            self::fail('the middleware has to fail');
        } catch (RuntimeException) {
        }

        $failing->fail = false;

        self::assertEquals(new Skill('php'), $next->hydrate(self::metadata(), ['name' => 'php'], []));
        self::assertSame(['name' => 'php'], $next->extract(self::metadata(), new Skill('php'), []));
        self::assertSame(['hydrate outer', 'extract outer', 'hydrate outer', 'extract outer'], $calls->getArrayCopy());
    }

    public function testWithoutMiddlewaresOnlyTheTransformerRuns(): void
    {
        $next = new Next([], self::transformer());

        $skill = $next->hydrate(self::metadata(), ['name' => 'php'], []);

        self::assertEquals(new Skill('php'), $skill);
        self::assertSame(['name' => 'php'], $next->extract(self::metadata(), $skill, []));
    }

    /** @return ReflectionTransformer<Skill> */
    private static function transformer(): ReflectionTransformer
    {
        return new ReflectionTransformer(self::metadata());
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

    /** @param ArrayObject<int, string> $calls */
    private static function recording(string $name, ArrayObject $calls): Middleware
    {
        return new class ($name, $calls) implements Middleware {
            /** @param ArrayObject<int, string> $calls */
            public function __construct(
                private readonly string $name,
                private readonly ArrayObject $calls,
            ) {
            }

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
                $this->calls[] = 'hydrate ' . $this->name;

                return $next->hydrate($metadata, $data, $context);
            }

            /**
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             */
            public function extract(ClassMetadata $metadata, object $object, array $context, Next $next): array
            {
                $this->calls[] = 'extract ' . $this->name;

                return $next->extract($metadata, $object, $context);
            }
        };
    }
}
