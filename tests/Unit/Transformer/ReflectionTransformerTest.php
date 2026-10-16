<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Transformer;

use Patchlevel\Hydrator\ClassExtractor;
use Patchlevel\Hydrator\ClassHydrator;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\NormalizationFailure;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\NestedObjectsDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use Patchlevel\Hydrator\Transformer\ReflectionTransformer;
use Patchlevel\Hydrator\Transformer\ReflectionTransformerFactory;
use Patchlevel\Hydrator\Transformer\TransformerResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ReflectionTransformer::class)]
#[CoversClass(ReflectionTransformerFactory::class)]
final class ReflectionTransformerTest extends TestCase
{
    public function testHydrateAndExtract(): void
    {
        $transformer = new ReflectionTransformer((new AttributeMetadataFactory())->metadata(Skill::class));

        $skill = $transformer->hydrate(['name' => 'php'], []);

        self::assertEquals(new Skill('php'), $skill);
        self::assertSame(['name' => 'php'], $transformer->extract($skill, []));
    }

    public function testHydrateObjectToPopulate(): void
    {
        $transformer = new ReflectionTransformer((new AttributeMetadataFactory())->metadata(Skill::class));
        $skill = new Skill('php');

        $result = $transformer->hydrate(['name' => 'symfony'], [Hydrator::OBJECT_TO_POPULATE => $skill]);

        self::assertSame($skill, $result);
        self::assertSame('symfony', $skill->name);
    }

    public function testFactoryCreatesReflectionTransformer(): void
    {
        $factory = new ReflectionTransformerFactory();
        $transformer = $factory->create(
            (new AttributeMetadataFactory())->metadata(Skill::class),
            $this->createStub(TransformerResolver::class),
        );

        self::assertEquals(new Skill('php'), $transformer->hydrate(['name' => 'php'], []));
    }

    public function testHydrateNestedObjectsWithTheChainsOfTheHydrator(): void
    {
        $skill = new Skill('php');
        $hydrator = $this->createMock(Hydrator::class);
        $hydrator->expects($this->never())->method('hydrate');
        $context = [Hydrator::HYDRATOR => $hydrator];

        $chain = $this->createMock(ClassHydrator::class);
        $chain->expects($this->exactly(4))
            ->method('hydrate')
            ->with(['name' => 'php'], $context)
            ->willReturn($skill);

        $resolver = $this->createMock(TransformerResolver::class);
        $resolver->method('hydrator')->willReturn($hydrator);
        // once per property, the chains are kept for the next calls
        $resolver->expects($this->exactly(2))
            ->method('classHydrator')
            ->with(Skill::class)
            ->willReturn($chain);

        $transformer = self::transformer($resolver);
        $data = ['name' => 'foo', 'skill' => ['name' => 'php'], 'skills' => [['name' => 'php'], null]];

        self::assertEquals(new NestedObjectsDto('foo', $skill, [$skill, null]), $transformer->hydrate($data, $context));
        self::assertEquals(new NestedObjectsDto('foo', $skill, [$skill, null]), $transformer->hydrate($data, $context));
    }

    public function testHydrateNullWithTheNormalizer(): void
    {
        $hydrator = $this->createMock(Hydrator::class);
        $hydrator->expects($this->never())->method('hydrate');

        $resolver = $this->createMock(TransformerResolver::class);
        $resolver->method('hydrator')->willReturn($hydrator);
        $resolver->expects($this->never())->method('classHydrator');

        $object = self::transformer($resolver)->hydrate(
            ['name' => 'foo', 'skill' => null, 'skills' => null],
            [Hydrator::HYDRATOR => $hydrator],
        );

        self::assertEquals(new NestedObjectsDto('foo'), $object);
    }

    public function testExtractNestedObjectsWithTheChainsOfTheHydrator(): void
    {
        $skill = new Skill('php');
        $hydrator = $this->createMock(Hydrator::class);
        $hydrator->expects($this->never())->method('extract');
        $context = [Hydrator::HYDRATOR => $hydrator];

        $chain = $this->createMock(ClassExtractor::class);
        $chain->expects($this->exactly(4))
            ->method('extract')
            ->with($skill, $context)
            ->willReturn(['name' => 'php']);

        $resolver = $this->createMock(TransformerResolver::class);
        $resolver->method('hydrator')->willReturn($hydrator);
        // once per property, the chains are kept for the next calls
        $resolver->expects($this->exactly(2))
            ->method('classExtractor')
            ->with(Skill::class)
            ->willReturn($chain);

        $transformer = self::transformer($resolver);
        $object = new NestedObjectsDto('foo', $skill, [$skill, null]);
        $expected = ['name' => 'foo', 'skill' => ['name' => 'php'], 'skills' => [['name' => 'php'], null], 'child' => null];

        self::assertSame($expected, $transformer->extract($object, $context));
        self::assertSame($expected, $transformer->extract($object, $context));
    }

    public function testExtractSubclassesWithTheHydrator(): void
    {
        $child = new ParentDto(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de'));
        $hydrator = $this->createMock(Hydrator::class);
        $context = [Hydrator::HYDRATOR => $hydrator];
        $hydrator->expects($this->once())
            ->method('extract')
            ->with($child, $context)
            ->willReturn(['profileId' => '1']);

        $resolver = $this->createMock(TransformerResolver::class);
        $resolver->method('hydrator')->willReturn($hydrator);
        $resolver->expects($this->never())->method('classExtractor');

        self::assertSame(
            ['name' => 'foo', 'skill' => null, 'skills' => null, 'child' => ['profileId' => '1']],
            self::transformer($resolver)->extract(new NestedObjectsDto('foo', child: $child), $context),
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

        $resolver = $this->createMock(TransformerResolver::class);
        $resolver->method('hydrator')->willReturn($this->createStub(Hydrator::class));
        $resolver->expects($this->never())->method('classHydrator');
        $resolver->expects($this->never())->method('classExtractor');

        $transformer = self::transformer($resolver);
        $object = $transformer->hydrate(['name' => 'foo', 'skill' => ['name' => 'php'], 'skills' => [['name' => 'php']]], $context);

        self::assertEquals(new NestedObjectsDto('foo', $skill, [$skill]), $object);
        self::assertSame(
            ['name' => 'foo', 'skill' => ['name' => 'php'], 'skills' => [['name' => 'php']], 'child' => null],
            $transformer->extract($object, $context),
        );
    }

    public function testNestedClassesWithAClassNormalizerUseTheNormalizer(): void
    {
        $skill = new Skill('php');
        $hydrator = $this->createMock(Hydrator::class);
        $context = [Hydrator::HYDRATOR => $hydrator];
        $hydrator->expects($this->exactly(2))
            ->method('hydrate')
            ->with(Skill::class, ['name' => 'php'], $context)
            ->willReturn($skill);
        $hydrator->expects($this->exactly(2))
            ->method('extract')
            ->with($skill, $context)
            ->willReturn(['name' => 'php']);

        // the hydrator has no chain for a class with a class normalizer
        $resolver = $this->createStub(TransformerResolver::class);
        $resolver->method('hydrator')->willReturn($hydrator);
        $resolver->method('classHydrator')->willReturn(null);
        $resolver->method('classExtractor')->willReturn(null);

        $transformer = self::transformer($resolver);
        $object = $transformer->hydrate(['name' => 'foo', 'skill' => ['name' => 'php'], 'skills' => [['name' => 'php']]], $context);

        self::assertEquals(new NestedObjectsDto('foo', $skill, [$skill]), $object);
        self::assertSame(
            ['name' => 'foo', 'skill' => ['name' => 'php'], 'skills' => [['name' => 'php']], 'child' => null],
            $transformer->extract($object, $context),
        );
    }

    public function testWithoutResolverTheNormalizerIsUsed(): void
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

        $transformer = new ReflectionTransformer((new AttributeMetadataFactory())->metadata(NestedObjectsDto::class));
        $object = $transformer->hydrate(['name' => 'foo', 'skill' => ['name' => 'php']], $context);

        self::assertEquals(new NestedObjectsDto('foo', $skill), $object);
        self::assertSame(
            ['name' => 'foo', 'skill' => ['name' => 'php'], 'skills' => null, 'child' => null],
            $transformer->extract($object, $context),
        );
    }

    public function testObjectCanBeExtractedAgainAfterAFailure(): void
    {
        $hydrator = $this->createStub(Hydrator::class);
        $hydrator->method('extract')->willThrowException(new RuntimeException('failed'));
        $context = [Hydrator::HYDRATOR => $hydrator];

        $transformer = new ReflectionTransformer((new AttributeMetadataFactory())->metadata(NestedObjectsDto::class));
        $object = new NestedObjectsDto('foo', new Skill('php'));

        try {
            $transformer->extract($object, $context);
            self::fail('the extraction has to fail');
        } catch (NormalizationFailure) {
        }

        // the object is no longer on the call stack, so it is no circular reference
        $this->expectException(NormalizationFailure::class);
        $transformer->extract($object, $context);
    }

    /** @return ReflectionTransformer<NestedObjectsDto> */
    private static function transformer(TransformerResolver $resolver): ReflectionTransformer
    {
        return new ReflectionTransformer(
            (new AttributeMetadataFactory())->metadata(NestedObjectsDto::class),
            resolver: $resolver,
        );
    }
}
