<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Middleware;

use ArrayObject;
use Patchlevel\Hydrator\ClassExtractor;
use Patchlevel\Hydrator\ClassHydrator;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\MiddlewareExtractor;
use Patchlevel\Hydrator\Middleware\MiddlewareHydrator;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use Patchlevel\Hydrator\Transformer\ReflectionTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MiddlewareHydrator::class)]
#[CoversClass(MiddlewareExtractor::class)]
final class MiddlewareChainTest extends TestCase
{
    public function testMiddlewaresAreCalledInOrder(): void
    {
        $calls = self::calls();
        $first = self::recording('first', $calls);
        $second = self::recording('second', $calls);
        $transformer = self::transformer();

        $hydrator = new MiddlewareHydrator($first, self::metadata(), new MiddlewareHydrator($second, self::metadata(), $transformer));
        $extractor = new MiddlewareExtractor($first, self::metadata(), new MiddlewareExtractor($second, self::metadata(), $transformer));

        $skill = $hydrator->hydrate(['name' => 'php'], []);
        $data = $extractor->extract($skill, []);

        self::assertEquals(new Skill('php'), $skill);
        self::assertSame(['name' => 'php'], $data);
        self::assertSame(
            [
                'hydrate first ' . Skill::class,
                'hydrate second ' . Skill::class,
                'extract first ' . Skill::class,
                'extract second ' . Skill::class,
            ],
            $calls->getArrayCopy(),
        );
    }

    public function testRestOfTheChainCanBeCalledTwice(): void
    {
        $calls = self::calls();
        $twice = new class implements Middleware {
            /**
             * @param ClassMetadata<T>     $metadata
             * @param array<string, mixed> $data
             * @param array<string, mixed> $context
             * @param ClassHydrator<T>     $next
             *
             * @return T
             *
             * @template T of object
             */
            public function hydrate(ClassMetadata $metadata, array $data, array $context, ClassHydrator $next): object
            {
                $next->hydrate($data, $context);

                return $next->hydrate($data, $context);
            }

            /**
             * @param ClassMetadata<T>     $metadata
             * @param T                    $object
             * @param array<string, mixed> $context
             * @param ClassExtractor<T>    $next
             *
             * @return array<string, mixed>
             *
             * @template T of object
             */
            public function extract(ClassMetadata $metadata, object $object, array $context, ClassExtractor $next): array
            {
                $next->extract($object, $context);

                return $next->extract($object, $context);
            }
        };

        $inner = self::recording('inner', $calls);
        $transformer = self::transformer();

        $hydrator = new MiddlewareHydrator($twice, self::metadata(), new MiddlewareHydrator($inner, self::metadata(), $transformer));
        $extractor = new MiddlewareExtractor($twice, self::metadata(), new MiddlewareExtractor($inner, self::metadata(), $transformer));

        $extractor->extract($hydrator->hydrate(['name' => 'php'], []), []);

        self::assertSame(
            [
                'hydrate inner ' . Skill::class,
                'hydrate inner ' . Skill::class,
                'extract inner ' . Skill::class,
                'extract inner ' . Skill::class,
            ],
            $calls->getArrayCopy(),
        );
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
             * @param ClassHydrator<T>     $next
             *
             * @return T
             *
             * @template T of object
             */
            public function hydrate(ClassMetadata $metadata, array $data, array $context, ClassHydrator $next): object
            {
                $this->calls[] = 'hydrate ' . $this->name . ' ' . $metadata->className;

                return $next->hydrate($data, $context);
            }

            /**
             * @param ClassMetadata<T>     $metadata
             * @param T                    $object
             * @param array<string, mixed> $context
             * @param ClassExtractor<T>    $next
             *
             * @return array<string, mixed>
             *
             * @template T of object
             */
            public function extract(ClassMetadata $metadata, object $object, array $context, ClassExtractor $next): array
            {
                $this->calls[] = 'extract ' . $this->name . ' ' . $metadata->className;

                return $next->extract($object, $context);
            }
        };
    }
}
