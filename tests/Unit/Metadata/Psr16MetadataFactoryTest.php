<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Metadata\Psr16MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use ReflectionClass;
use stdClass;

#[CoversClass(Psr16MetadataFactory::class)]
final class Psr16MetadataFactoryTest extends TestCase
{
    public function testMetadataWithHit(): void
    {
        $classMetadata = new ClassMetadata(new ReflectionClass(stdClass::class));

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->with(stdClass::class)
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
            ->with(stdClass::class)
            ->willReturn(null);
        $cache->expects(self::once())
            ->method('set')
            ->with(stdClass::class, $classMetadata);

        $innerFactory = $this->createMock(MetadataFactory::class);
        $innerFactory->expects(self::once())
            ->method('metadata')
            ->with(stdClass::class)
            ->willReturn($classMetadata);

        $factory = new Psr16MetadataFactory($innerFactory, $cache);
        $result = $factory->metadata(stdClass::class);

        self::assertSame($classMetadata, $result);
    }
}
