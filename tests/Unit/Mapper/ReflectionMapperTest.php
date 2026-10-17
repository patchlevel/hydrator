<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Mapper;

use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Mapper\MapperFactory;
use Patchlevel\Hydrator\Mapper\ReflectionMapper;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\NormalizationFailure;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\NestedObjectsDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ReflectionMapper::class)]
final class ReflectionMapperTest extends TestCase
{
    public function testHydrateAndExtract(): void
    {
        $mapper = new ReflectionMapper((new AttributeMetadataFactory())->metadata(Skill::class));

        $skill = $mapper->hydrate(['name' => 'php'], []);

        self::assertEquals(new Skill('php'), $skill);
        self::assertSame(['name' => 'php'], $mapper->extract($skill, []));
    }

    public function testHydrateObjectToPopulate(): void
    {
        $mapper = new ReflectionMapper((new AttributeMetadataFactory())->metadata(Skill::class));
        $skill = new Skill('php');

        $result = $mapper->hydrate(['name' => 'symfony'], [Hydrator::OBJECT_TO_POPULATE => $skill]);

        self::assertSame($skill, $result);
        self::assertSame('symfony', $skill->name);
    }

    public function testHydrateNestedObjectsWithTheMapperOfTheirClass(): void
    {
        $skill = new Skill('php');

        $skillMapper = $this->createMock(Mapper::class);
        $skillMapper->expects($this->exactly(4))
            ->method('hydrate')
            ->with(['name' => 'php'], $this->anything())
            ->willReturn($skill);

        $hydrator = self::hydrator($skillMapper);
        $mapper = self::mapper($hydrator);
        $context = [Hydrator::HYDRATOR => $hydrator];
        $data = ['name' => 'foo', 'skill' => ['name' => 'php'], 'skills' => [['name' => 'php'], null]];

        self::assertEquals(new NestedObjectsDto('foo', $skill, [$skill, null]), $mapper->hydrate($data, $context));
        self::assertEquals(new NestedObjectsDto('foo', $skill, [$skill, null]), $mapper->hydrate($data, $context));
    }

    public function testHydrateNullWithTheNormalizer(): void
    {
        $skillMapper = $this->createMock(Mapper::class);
        $skillMapper->expects($this->never())->method('hydrate');

        $hydrator = self::hydrator($skillMapper);

        $object = self::mapper($hydrator)->hydrate(
            ['name' => 'foo', 'skill' => null, 'skills' => null],
            [Hydrator::HYDRATOR => $hydrator],
        );

        self::assertEquals(new NestedObjectsDto('foo'), $object);
    }

    public function testExtractNestedObjectsWithTheMapperOfTheirClass(): void
    {
        $skill = new Skill('php');

        $skillMapper = $this->createMock(Mapper::class);
        $skillMapper->expects($this->exactly(4))
            ->method('extract')
            ->with($skill, $this->anything())
            ->willReturn(['name' => 'PHP']);

        $hydrator = self::hydrator($skillMapper);
        $mapper = self::mapper($hydrator);
        $context = [Hydrator::HYDRATOR => $hydrator];
        $object = new NestedObjectsDto('foo', $skill, [$skill, null]);
        $expected = ['name' => 'foo', 'skill' => ['name' => 'PHP'], 'skills' => [['name' => 'PHP'], null], 'child' => null];

        self::assertSame($expected, $mapper->extract($object, $context));
        self::assertSame($expected, $mapper->extract($object, $context));
    }

    public function testExtractSubclassesWithTheHydrator(): void
    {
        $hydrator = new StackHydrator();
        $child = new ParentDto(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de'));

        self::assertSame(
            [
                'name' => 'foo',
                'skill' => null,
                'skills' => null,
                'child' => ['profileId' => '1', 'email' => 'info@patchlevel.de'],
            ],
            self::mapper($hydrator)->extract(new NestedObjectsDto('foo', child: $child), [Hydrator::HYDRATOR => $hydrator]),
        );
    }

    public function testNestedObjectsOfAnotherHydratorUseTheNormalizer(): void
    {
        $skill = new Skill('php');
        $other = $this->createMock(Hydrator::class);
        $context = [Hydrator::HYDRATOR => $other];
        $other->expects($this->exactly(2))
            ->method('hydrate')
            ->with(Skill::class, ['name' => 'php'], $context)
            ->willReturn($skill);
        $other->expects($this->exactly(2))
            ->method('extract')
            ->with($skill, $context)
            ->willReturn(['name' => 'php']);

        $skillMapper = $this->createMock(Mapper::class);
        $skillMapper->expects($this->never())->method('hydrate');
        $skillMapper->expects($this->never())->method('extract');

        $mapper = self::mapper(self::hydrator($skillMapper));
        $object = $mapper->hydrate(['name' => 'foo', 'skill' => ['name' => 'php'], 'skills' => [['name' => 'php']]], $context);

        self::assertEquals(new NestedObjectsDto('foo', $skill, [$skill]), $object);
        self::assertSame(
            ['name' => 'foo', 'skill' => ['name' => 'php'], 'skills' => [['name' => 'php']], 'child' => null],
            $mapper->extract($object, $context),
        );
    }

    public function testNestedClassesWithAClassNormalizerUseTheNormalizer(): void
    {
        $classNormalizer = $this->createMock(Normalizer::class);
        $classNormalizer->expects($this->exactly(2))
            ->method('denormalize')
            ->willReturnCallback(static fn (array $data): Skill => new Skill('normalized'));
        $classNormalizer->expects($this->exactly(2))
            ->method('normalize')
            ->willReturn(['name' => 'normalized']);

        $metadataFactory = new class ($classNormalizer) implements MetadataFactory {
            public function __construct(
                private readonly Normalizer $classNormalizer,
            ) {
            }

            /**
             * @param class-string<T> $class
             *
             * @return ClassMetadata<T>
             *
             * @template T of object
             */
            public function metadata(string $class): ClassMetadata
            {
                $metadata = (new AttributeMetadataFactory())->metadata($class);

                if ($class === Skill::class) {
                    $metadata->normalizer = $this->classNormalizer;
                }

                return $metadata;
            }
        };

        $hydrator = new StackHydrator($metadataFactory);
        $mapper = self::mapper($hydrator);
        $context = [Hydrator::HYDRATOR => $hydrator];
        $object = $mapper->hydrate(['name' => 'foo', 'skill' => ['name' => 'php'], 'skills' => [['name' => 'php']]], $context);

        self::assertEquals(new NestedObjectsDto('foo', new Skill('normalized'), [new Skill('normalized')]), $object);
        self::assertSame(
            ['name' => 'foo', 'skill' => ['name' => 'normalized'], 'skills' => [['name' => 'normalized']], 'child' => null],
            $mapper->extract($object, $context),
        );
    }

    public function testWithoutHydratorTheNormalizerIsUsed(): void
    {
        $skill = new Skill('php');
        $hydrator = $this->createMock(Hydrator::class);
        $context = [Hydrator::HYDRATOR => $hydrator];
        $hydrator->expects($this->once())
            ->method('hydrate')
            ->with(Skill::class, ['name' => 'php'], $context)
            ->willReturn($skill);
        $hydrator->expects($this->once())
            ->method('extract')
            ->with($skill, $context)
            ->willReturn(['name' => 'php']);

        $mapper = new ReflectionMapper((new AttributeMetadataFactory())->metadata(NestedObjectsDto::class));
        $object = $mapper->hydrate(['name' => 'foo', 'skill' => ['name' => 'php']], $context);

        self::assertEquals(new NestedObjectsDto('foo', $skill), $object);
        self::assertSame(
            ['name' => 'foo', 'skill' => ['name' => 'php'], 'skills' => null, 'child' => null],
            $mapper->extract($object, $context),
        );
    }

    public function testObjectCanBeExtractedAgainAfterAFailure(): void
    {
        $hydrator = $this->createStub(Hydrator::class);
        $hydrator->method('extract')->willThrowException(new RuntimeException('failed'));
        $context = [Hydrator::HYDRATOR => $hydrator];

        $mapper = new ReflectionMapper((new AttributeMetadataFactory())->metadata(NestedObjectsDto::class));
        $object = new NestedObjectsDto('foo', new Skill('php'));

        try {
            $mapper->extract($object, $context);
            self::fail('the extraction has to fail');
        } catch (NormalizationFailure) {
        }

        // the object is no longer on the call stack, so it is no circular reference
        $this->expectException(NormalizationFailure::class);
        $mapper->extract($object, $context);
    }

    /** @return ReflectionMapper<NestedObjectsDto> */
    private static function mapper(StackHydrator $hydrator): ReflectionMapper
    {
        return new ReflectionMapper(
            (new AttributeMetadataFactory())->metadata(NestedObjectsDto::class),
            $hydrator,
        );
    }

    /** @param Mapper<object> $skillMapper */
    private static function hydrator(Mapper $skillMapper): StackHydrator
    {
        return new StackHydrator(mapperFactories: [
            new class ($skillMapper) implements MapperFactory {
                /** @param Mapper<object> $skillMapper */
                public function __construct(
                    private readonly Mapper $skillMapper,
                ) {
                }

                /**
                 * @param ClassMetadata<T> $metadata
                 *
                 * @return Mapper<T>|null
                 *
                 * @template T of object
                 */
                public function create(ClassMetadata $metadata, StackHydrator $hydrator): Mapper|null
                {
                    /** @var Mapper<T>|null $mapper */
                    $mapper = $metadata->className === Skill::class ? $this->skillMapper : null;

                    return $mapper;
                }
            },
        ]);
    }
}
