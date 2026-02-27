<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Metadata\Psr6MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;
use stdClass;

#[CoversClass(Psr6MetadataFactory::class)]
final class Psr6MetadataFactoryTest extends TestCase
{
    public function testMetadataWithHit(): void
    {
        $classMetadata = new ClassMetadata(new ReflectionClass(stdClass::class));

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects(self::once())
            ->method('isHit')
            ->willReturn(true);
        $item->expects(self::once())
            ->method('get')
            ->willReturn($classMetadata);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects(self::once())
            ->method('getItem')
            ->with(stdClass::class)
            ->willReturn($item);

        $innerFactory = $this->createMock(MetadataFactory::class);
        $innerFactory->expects(self::never())
            ->method('metadata');

        $factory = new Psr6MetadataFactory($innerFactory, $cache);
        $result = $factory->metadata(stdClass::class);

        self::assertSame($classMetadata, $result);
    }

    public function testMetadataWithMiss(): void
    {
        $classMetadata = new ClassMetadata(new ReflectionClass(stdClass::class));

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects(self::once())
            ->method('isHit')
            ->willReturn(false);
        $item->expects(self::once())
            ->method('set')
            ->with($classMetadata);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects(self::once())
            ->method('getItem')
            ->with(stdClass::class)
            ->willReturn($item);
        $cache->expects(self::once())
            ->method('save')
            ->with($item);

        $innerFactory = $this->createMock(MetadataFactory::class);
        $innerFactory->expects(self::once())
            ->method('metadata')
            ->with(stdClass::class)
            ->willReturn($classMetadata);

        $factory = new Psr6MetadataFactory($innerFactory, $cache);
        $result = $factory->metadata(stdClass::class);

        self::assertSame($classMetadata, $result);
    }
}
