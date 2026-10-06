<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Transformer;

use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use Patchlevel\Hydrator\Transformer\ReflectionTransformer;
use Patchlevel\Hydrator\Transformer\ReflectionTransformerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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
        $transformer = $factory->create((new AttributeMetadataFactory())->metadata(Skill::class), new StackHydrator());

        self::assertEquals(new Skill('php'), $transformer->hydrate(['name' => 'php'], []));
    }
}
