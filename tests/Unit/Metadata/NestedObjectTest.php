<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Metadata\NestedObject;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NestedObject::class)]
final class NestedObjectTest extends TestCase
{
    public function testObjectNormalizer(): void
    {
        self::assertEquals(new NestedObject(Skill::class, false), NestedObject::of(new ObjectNormalizer(Skill::class)));
    }

    public function testArrayOfObjects(): void
    {
        self::assertEquals(
            new NestedObject(Skill::class, true),
            NestedObject::of(new ArrayNormalizer(new ObjectNormalizer(Skill::class))),
        );
    }

    public function testOtherNormalizers(): void
    {
        self::assertNull(NestedObject::of(new DateTimeImmutableNormalizer()));
        self::assertNull(NestedObject::of(new ArrayNormalizer(new DateTimeImmutableNormalizer())));
    }

    public function testObjectNormalizerWithoutClass(): void
    {
        self::assertNull(NestedObject::of(new ObjectNormalizer()));
    }

    public function testUnknownClass(): void
    {
        /** @phpstan-ignore argument.type */
        self::assertNull(NestedObject::of(new ObjectNormalizer('Unknown')));
    }
}
