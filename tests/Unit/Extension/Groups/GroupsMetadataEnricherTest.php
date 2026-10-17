<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Groups;

use Patchlevel\Hydrator\Extension\Groups\Attribute\Groups;
use Patchlevel\Hydrator\Extension\Groups\GroupsMetadataEnricher;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\UserFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GroupsMetadataEnricher::class)]
#[CoversClass(Groups::class)]
final class GroupsMetadataEnricherTest extends TestCase
{
    public function testEnrich(): void
    {
        $metadata = (new AttributeMetadataFactory())->metadata(UserFixture::class);

        (new GroupsMetadataEnricher())->enrich($metadata);

        self::assertSame(['public', 'admin'], $metadata->properties['id']->extras[Groups::class]);
        self::assertSame(['public'], $metadata->properties['name']->extras[Groups::class]);
        self::assertSame(['admin'], $metadata->properties['email']->extras[Groups::class]);
        self::assertArrayNotHasKey(Groups::class, $metadata->properties['internal']->extras);
        self::assertTrue($metadata->extras[Groups::class]);
    }

    public function testClassWithoutGroups(): void
    {
        $object = new class {
            public string $name = 'foo';
        };

        $metadata = (new AttributeMetadataFactory())->metadata($object::class);

        (new GroupsMetadataEnricher())->enrich($metadata);

        self::assertArrayNotHasKey(Groups::class, $metadata->extras);
        self::assertArrayNotHasKey(Groups::class, $metadata->properties['name']->extras);
    }
}
