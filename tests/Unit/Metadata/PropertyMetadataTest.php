<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\NestedObject;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;
use Patchlevel\Hydrator\Tests\Unit\Fixture\NestedObjectsDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PropertyMetadata::class)]
final class PropertyMetadataTest extends TestCase
{
    public function testNested(): void
    {
        $properties = (new AttributeMetadataFactory())->metadata(NestedObjectsDto::class)->properties;

        self::assertNull($properties['name']->nested());
        self::assertEquals(new NestedObject(Skill::class, false), $properties['skill']->nested());
        self::assertEquals(new NestedObject(Skill::class, true), $properties['skills']->nested());
    }
}
