<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function serialize;
use function unserialize;

#[CoversClass(ClassMetadata::class)]
final class ClassMetadataTest extends TestCase
{
    public function testSerialize(): void
    {
        $classMetadata = (new AttributeMetadataFactory())->metadata(ProfileCreated::class);

        $unserialized = unserialize(serialize($classMetadata));

        self::assertInstanceOf(ClassMetadata::class, $unserialized);
        self::assertSame(ProfileCreated::class, $unserialized->className);
        self::assertEquals($classMetadata, $unserialized);
    }
}
