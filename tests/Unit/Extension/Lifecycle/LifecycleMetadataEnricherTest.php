<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Lifecycle;

use LogicException;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PostExtract;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PostHydrate;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PreExtract;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PreHydrate;
use Patchlevel\Hydrator\Extension\Lifecycle\Lifecycle;
use Patchlevel\Hydrator\Extension\Lifecycle\LifecycleMetadataEnricher;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Tests\Unit\Extension\Lifecycle\Fixture\LifecycleFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LifecycleMetadataEnricher::class)]
final class LifecycleMetadataEnricherTest extends TestCase
{
    public function testEnrich(): void
    {
        $metadata = $this->metadata(LifecycleFixture::class);

        self::assertArrayHasKey(Lifecycle::class, $metadata->extras);
        $lifecycle = $metadata->extras[Lifecycle::class];

        self::assertInstanceOf(Lifecycle::class, $lifecycle);
        self::assertSame('preHydrate', $lifecycle->preHydrate);
        self::assertSame('postHydrate', $lifecycle->postHydrate);
        self::assertSame('preExtract', $lifecycle->preExtract);
        self::assertSame('postExtract', $lifecycle->postExtract);
    }

    public function testNoLifecycleAttributes(): void
    {
        $object = new class {
        };

        $metadata = $this->metadata($object::class);

        self::assertArrayNotHasKey(Lifecycle::class, $metadata->extras);
    }

    public function testNonStaticPreHydrate(): void
    {
        $object = new class {
            #[PreHydrate]
            public function preHydrate(): void
            {
            }
        };

        $this->expectException(LogicException::class);
        $this->metadata($object::class);
    }

    public function testNonStaticPostHydrate(): void
    {
        $object = new class {
            #[PostHydrate]
            public function postHydrate(): void
            {
            }
        };

        $this->expectException(LogicException::class);
        $this->metadata($object::class);
    }

    public function testNonStaticPreExtract(): void
    {
        $object = new class {
            #[PreExtract]
            public function preExtract(): void
            {
            }
        };

        $this->expectException(LogicException::class);
        $this->metadata($object::class);
    }

    public function testNonStaticPostExtract(): void
    {
        $object = new class {
            #[PostExtract]
            public function postExtract(): void
            {
            }
        };

        $this->expectException(LogicException::class);
        $this->metadata($object::class);
    }

    /** @param class-string $class */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = (new AttributeMetadataFactory())->metadata($class);
        (new LifecycleMetadataEnricher())->enrich($metadata);

        return $metadata;
    }
}
