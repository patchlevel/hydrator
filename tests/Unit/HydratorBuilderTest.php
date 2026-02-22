<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\Guesser\ChainGuesser;
use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\HydratorBuilder;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\EnrichingMetadataFactory;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\Metadata\Psr16MetadataFactory;
use Patchlevel\Hydrator\Metadata\Psr6MetadataFactory;
use Patchlevel\Hydrator\MetadataHydrator;
use Patchlevel\Hydrator\Middleware\Middleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;
use ReflectionProperty;

#[CoversClass(HydratorBuilder::class)]
final class HydratorBuilderTest extends TestCase
{
    public function testBuild(): void
    {
        $hydrator = (new HydratorBuilder())->build();

        self::assertInstanceOf(MetadataHydrator::class, $hydrator);
    }

    public function testAddMiddlewareWithPriority(): void
    {
        $middleware1 = $this->createMock(Middleware::class);
        $middleware2 = $this->createMock(Middleware::class);

        $builder = new HydratorBuilder();
        $builder->addMiddleware($middleware1, 10);
        $builder->addMiddleware($middleware2, 20);

        $hydrator = $builder->build();

        $reflection = new ReflectionProperty(MetadataHydrator::class, 'middlewares');
        $middlewares = $reflection->getValue($hydrator);

        self::assertSame([$middleware2, $middleware1], $middlewares);
    }

    public function testAddMetadataEnricherWithPriority(): void
    {
        $enricher1 = $this->createMock(MetadataEnricher::class);
        $enricher2 = $this->createMock(MetadataEnricher::class);

        $builder = new HydratorBuilder();
        $builder->addMetadataEnricher($enricher1, 10);
        $builder->addMetadataEnricher($enricher2, 20);

        $hydrator = $builder->build();

        $reflection = new ReflectionProperty(MetadataHydrator::class, 'metadataFactory');
        $enrichingMetadataFactory = $reflection->getValue($hydrator);

        self::assertInstanceOf(EnrichingMetadataFactory::class, $enrichingMetadataFactory);

        $reflection = new ReflectionProperty(EnrichingMetadataFactory::class, 'enrichers');
        $enrichers = $reflection->getValue($enrichingMetadataFactory);

        self::assertSame([$enricher2, $enricher1], $enrichers);
    }

    public function testAddGuesserWithPriority(): void
    {
        $guesser1 = $this->createMock(Guesser::class);
        $guesser2 = $this->createMock(Guesser::class);

        $builder = new HydratorBuilder();
        $builder->addGuesser($guesser1, 10);
        $builder->addGuesser($guesser2, 20);

        $hydrator = $builder->build();

        $reflection = new ReflectionProperty(MetadataHydrator::class, 'metadataFactory');
        $enrichingMetadataFactory = $reflection->getValue($hydrator);

        self::assertInstanceOf(EnrichingMetadataFactory::class, $enrichingMetadataFactory);

        $reflection = new ReflectionProperty(EnrichingMetadataFactory::class, 'factory');
        $metadataFactory = $reflection->getValue($enrichingMetadataFactory);

        self::assertInstanceOf(AttributeMetadataFactory::class, $metadataFactory);

        $reflection = new ReflectionProperty(AttributeMetadataFactory::class, 'guesser');
        $guesser = $reflection->getValue($metadataFactory);

        self::assertInstanceOf(ChainGuesser::class, $guesser);

        $reflection = new ReflectionProperty(ChainGuesser::class, 'guessers');
        $guessers = $reflection->getValue($guesser);

        self::assertSame([$guesser2, $guesser1], $guessers);
    }

    public function testEnableDefaultLazy(): void
    {
        $builder = new HydratorBuilder();
        $builder->enableDefaultLazy();

        $hydrator = $builder->build();

        $reflection = new ReflectionProperty(MetadataHydrator::class, 'defaultLazy');
        self::assertTrue($reflection->getValue($hydrator));
    }

    public function testUseExtension(): void
    {
        $extension = $this->createMock(Extension::class);
        $builder = new HydratorBuilder();

        $extension->expects(self::once())
            ->method('configure')
            ->with($builder);

        $builder->useExtension($extension);
    }

    public function testCachePsr6(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);

        $builder = new HydratorBuilder();
        $builder->setCache($cache);

        $hydrator = $builder->build();

        $reflection = new ReflectionProperty(MetadataHydrator::class, 'metadataFactory');
        $factory = $reflection->getValue($hydrator);

        self::assertInstanceOf(Psr6MetadataFactory::class, $factory);
    }

    public function testCachePsr16(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $builder = new HydratorBuilder();
        $builder->setCache($cache);

        $hydrator = $builder->build();

        $reflection = new ReflectionProperty(MetadataHydrator::class, 'metadataFactory');
        $factory = $reflection->getValue($hydrator);

        self::assertInstanceOf(Psr16MetadataFactory::class, $factory);
    }
}
