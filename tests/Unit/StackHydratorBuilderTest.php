<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit;

use Patchlevel\Hydrator\DecoratorsNotApplied;
use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\Guesser\ChainGuesser;
use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\HydratorDecorator;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\EnrichingMetadataFactory;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\Metadata\Psr16MetadataFactory;
use Patchlevel\Hydrator\Metadata\Psr6MetadataFactory;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Transformer\ChainTransformerFactory;
use Patchlevel\Hydrator\Transformer\ClassTransformerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;
use ReflectionProperty;

#[CoversClass(StackHydratorBuilder::class)]
final class StackHydratorBuilderTest extends TestCase
{
    public function testAddMiddlewareWithPriority(): void
    {
        $middleware1 = $this->createMock(Middleware::class);
        $middleware2 = $this->createMock(Middleware::class);

        $builder = new StackHydratorBuilder();
        $builder->addMiddleware($middleware1, 10);
        $builder->addMiddleware($middleware2, 20);

        $hydrator = $builder->build();

        $reflection = new ReflectionProperty(StackHydrator::class, 'middlewares');
        $middlewares = $reflection->getValue($hydrator);

        self::assertSame([$middleware2, $middleware1], $middlewares);
        self::assertSame([$middleware2, $middleware1], $builder->middlewares());
    }

    public function testAddTransformerFactoryWithPriority(): void
    {
        $factory1 = $this->createMock(ClassTransformerFactory::class);
        $factory2 = $this->createMock(ClassTransformerFactory::class);

        $builder = new StackHydratorBuilder();
        $builder->addMiddleware($this->createMock(Middleware::class));
        $builder->addTransformerFactory($factory1, 10);
        $builder->addTransformerFactory($factory2, 20);

        $reflection = new ReflectionProperty(StackHydrator::class, 'transformerFactory');

        self::assertEquals(new ChainTransformerFactory([$factory2, $factory1]), $reflection->getValue($builder->build()));
        self::assertSame([$factory2, $factory1], $builder->transformerFactories());
        self::assertNull($reflection->getValue((new StackHydratorBuilder())->build()));
    }

    public function testMetadataFactoryIsTheOneOfTheHydrator(): void
    {
        $builder = new StackHydratorBuilder();
        $builder->addMiddleware($this->createMock(Middleware::class));
        $builder->setCache($this->createMock(CacheInterface::class));

        $reflection = new ReflectionProperty(StackHydrator::class, 'metadataFactory');

        self::assertInstanceOf(Psr16MetadataFactory::class, $builder->metadataFactory());
        self::assertEquals($builder->metadataFactory(), $reflection->getValue($builder->build()));
    }

    public function testAddMetadataEnricherWithPriority(): void
    {
        $enricher1 = $this->createMock(MetadataEnricher::class);
        $enricher2 = $this->createMock(MetadataEnricher::class);

        $builder = new StackHydratorBuilder();
        $builder->addMetadataEnricher($enricher1, 10);
        $builder->addMetadataEnricher($enricher2, 20);

        $builder->addMiddleware($this->createMock(Middleware::class));

        $hydrator = $builder->build();

        $reflection = new ReflectionProperty(StackHydrator::class, 'metadataFactory');
        $enrichingMetadataFactory = $reflection->getValue($hydrator);

        self::assertInstanceOf(EnrichingMetadataFactory::class, $enrichingMetadataFactory);

        $reflection = new ReflectionProperty(EnrichingMetadataFactory::class, 'enrichers');
        $enrichers = $reflection->getValue($enrichingMetadataFactory);

        self::assertSame([$enricher2, $enricher1], $enrichers);
        self::assertSame([$enricher2, $enricher1], $builder->metadataEnrichers());
    }

    public function testAddGuesserWithPriority(): void
    {
        $guesser1 = $this->createMock(Guesser::class);
        $guesser2 = $this->createMock(Guesser::class);

        $builder = new StackHydratorBuilder();
        $builder->addGuesser($guesser1, 10);
        $builder->addGuesser($guesser2, 20);

        $builder->addMiddleware($this->createMock(Middleware::class));

        $hydrator = $builder->build();

        $reflection = new ReflectionProperty(StackHydrator::class, 'metadataFactory');
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
        self::assertSame([$guesser2, $guesser1], $builder->guessers());
    }

    public function testEnableDefaultLazy(): void
    {
        $builder = new StackHydratorBuilder();
        $builder->enableDefaultLazy();

        $builder->addMiddleware($this->createMock(Middleware::class));

        $hydrator = $builder->build();

        $reflection = new ReflectionProperty(StackHydrator::class, 'defaultLazy');
        self::assertTrue($reflection->getValue($hydrator));
        self::assertTrue($builder->defaultLazy());
    }

    public function testUseExtension(): void
    {
        $extension = $this->createMock(Extension::class);
        $builder = new StackHydratorBuilder();

        $extension->expects(self::once())
            ->method('configure')
            ->with($builder);

        $builder->useExtension($extension);
    }

    public function testCachePsr6(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);

        $builder = new StackHydratorBuilder();
        $builder->setCache($cache);
        $builder->addMiddleware($this->createMock(Middleware::class));

        $hydrator = $builder->build();

        $reflection = new ReflectionProperty(StackHydrator::class, 'metadataFactory');
        $factory = $reflection->getValue($hydrator);

        self::assertInstanceOf(Psr6MetadataFactory::class, $factory);
    }

    public function testCachePsr16(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $builder = new StackHydratorBuilder();
        $builder->setCache($cache);
        $builder->addMiddleware($this->createMock(Middleware::class));

        $hydrator = $builder->build();

        $reflection = new ReflectionProperty(StackHydrator::class, 'metadataFactory');
        $factory = $reflection->getValue($hydrator);

        self::assertInstanceOf(Psr16MetadataFactory::class, $factory);
    }

    public function testBuildHydratorWithoutDecorators(): void
    {
        $builder = new StackHydratorBuilder();
        $builder->addMiddleware($this->createMock(Middleware::class));

        self::assertInstanceOf(StackHydrator::class, $builder->buildHydrator());
    }

    public function testBuildHydratorAppliesDecoratorsByPriority(): void
    {
        $inner = $this->createMock(Hydrator::class);
        $outer = $this->createMock(Hydrator::class);

        $innerDecorator = $this->createMock(HydratorDecorator::class);
        $innerDecorator
            ->expects($this->once())
            ->method('decorate')
            ->willReturnCallback(static function (Hydrator $hydrator) use ($inner): Hydrator {
                self::assertInstanceOf(StackHydrator::class, $hydrator);

                return $inner;
            });

        $outerDecorator = $this->createMock(HydratorDecorator::class);
        $outerDecorator
            ->expects($this->once())
            ->method('decorate')
            ->willReturnCallback(static function (Hydrator $hydrator) use ($inner, $outer): Hydrator {
                self::assertSame($inner, $hydrator);

                return $outer;
            });

        $builder = new StackHydratorBuilder();
        $builder->addMiddleware($this->createMock(Middleware::class));
        $builder->addDecorator($outerDecorator, 10);
        $builder->addDecorator($innerDecorator, -10);

        self::assertSame([$innerDecorator, $outerDecorator], $builder->decorators());
        self::assertSame($outer, $builder->buildHydrator());
    }

    public function testBuildFailsWithDecorators(): void
    {
        $decorator = $this->createMock(HydratorDecorator::class);
        $decorator->expects($this->never())->method('decorate');

        $builder = new StackHydratorBuilder();
        $builder->addMiddleware($this->createMock(Middleware::class));
        $builder->addDecorator($decorator);

        $this->expectException(DecoratorsNotApplied::class);
        $this->expectExceptionMessage('Decorators are registered but build() can not apply them, use buildHydrator() instead.');

        $builder->build();
    }
}
