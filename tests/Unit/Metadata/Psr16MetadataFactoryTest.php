<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\CacheKey;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Metadata\Psr16MetadataFactory;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use ReflectionClass;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

#[CoversClass(Psr16MetadataFactory::class)]
final class Psr16MetadataFactoryTest extends TestCase
{
    public function testMetadataWithHit(): void
    {
        $classMetadata = new ClassMetadata(new ReflectionClass(stdClass::class));

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->with(CacheKey::forClass(stdClass::class))
            ->willReturn($classMetadata);

        $innerFactory = $this->createMock(MetadataFactory::class);
        $innerFactory->expects(self::never())
            ->method('metadata');

        $factory = new Psr16MetadataFactory($innerFactory, $cache);
        $result = $factory->metadata(stdClass::class);

        self::assertSame($classMetadata, $result);
    }

    public function testMetadataWithMiss(): void
    {
        $classMetadata = new ClassMetadata(new ReflectionClass(stdClass::class));

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->with(CacheKey::forClass(stdClass::class))
            ->willReturn(null);
        $cache->expects(self::once())
            ->method('set')
            ->with(CacheKey::forClass(stdClass::class), $classMetadata);

        $innerFactory = $this->createMock(MetadataFactory::class);
        $innerFactory->expects(self::once())
            ->method('metadata')
            ->with(stdClass::class)
            ->willReturn($classMetadata);

        $factory = new Psr16MetadataFactory($innerFactory, $cache);
        $result = $factory->metadata(stdClass::class);

        self::assertSame($classMetadata, $result);
    }

    public function testMetadataIgnoresEntryOfOtherClass(): void
    {
        $otherMetadata = new ClassMetadata(new ReflectionClass(ProfileCreated::class));
        $classMetadata = new ClassMetadata(new ReflectionClass(stdClass::class));

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->with(CacheKey::forClass(stdClass::class))
            ->willReturn($otherMetadata);
        $cache->expects(self::once())
            ->method('set')
            ->with(CacheKey::forClass(stdClass::class), $classMetadata);

        $innerFactory = $this->createMock(MetadataFactory::class);
        $innerFactory->expects(self::once())
            ->method('metadata')
            ->with(stdClass::class)
            ->willReturn($classMetadata);

        $factory = new Psr16MetadataFactory($innerFactory, $cache);
        $result = $factory->metadata(stdClass::class);

        self::assertSame($classMetadata, $result);
    }

    public function testMetadataWithSymfonyCache(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $factory = new Psr16MetadataFactory(new AttributeMetadataFactory(), $cache);

        $metadata = $factory->metadata(ProfileCreated::class);

        self::assertSame(ProfileCreated::class, $metadata->className);
        self::assertTrue($cache->has(CacheKey::forClass(ProfileCreated::class)));
        self::assertEquals($metadata, $factory->metadata(ProfileCreated::class));
    }
}
