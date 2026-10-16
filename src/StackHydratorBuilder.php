<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Guesser\BuiltInGuesser;
use Patchlevel\Hydrator\Guesser\ChainGuesser;
use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Mapper\MapperFactory;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\EnrichingMetadataFactory;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\Metadata\Psr16MetadataFactory;
use Patchlevel\Hydrator\Metadata\Psr6MetadataFactory;
use Patchlevel\Hydrator\Middleware\Middleware;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;

use function array_merge;
use function krsort;

final class StackHydratorBuilder
{
    private bool $defaultLazy = false;

    /** @var array<int, list<Middleware>> */
    private array $middlewares = [];

    /** @var array<int, list<MetadataEnricher>> */
    private array $metadataEnrichers = [];

    /** @var array<int, list<Guesser>> */
    private array $guessers = [];

    /** @var array<int, list<MapperFactory>> */
    private array $mapperFactories = [];

    private CacheItemPoolInterface|CacheInterface|null $cache = null;

    /** @return $this */
    public function addMiddleware(Middleware $middleware, int $priority = Extension::PRIORITY_BEFORE_MAPPING): static
    {
        $this->middlewares[$priority][] = $middleware;

        return $this;
    }

    /** @return $this */
    public function addMetadataEnricher(MetadataEnricher $enricher, int $priority = 0): static
    {
        $this->metadataEnrichers[$priority][] = $enricher;

        return $this;
    }

    /**
     * Guessers with a higher priority are asked first, the {@see BuiltInGuesser} is always asked last.
     *
     * @return $this
     */
    public function addGuesser(Guesser $guesser, int $priority = 0): static
    {
        $this->guessers[$priority][] = $guesser;

        return $this;
    }

    /**
     * Factories with a higher priority are asked first, classes without a mapper of a factory are mapped with the
     * {@see ReflectionMapper}.
     *
     * @return $this
     */
    public function addMapperFactory(MapperFactory $factory, int $priority = 0): static
    {
        $this->mapperFactories[$priority][] = $factory;

        return $this;
    }

    public function enableDefaultLazy(bool $lazy = true): static
    {
        $this->defaultLazy = $lazy;

        return $this;
    }

    public function useExtension(Extension $extension): static
    {
        $extension->configure($this);

        return $this;
    }

    public function setCache(CacheItemPoolInterface|CacheInterface|null $cache): static
    {
        $this->cache = $cache;

        return $this;
    }

    public function build(): StackHydrator
    {
        $metadataFactory = new EnrichingMetadataFactory(
            new AttributeMetadataFactory(
                guesser: new ChainGuesser([...$this->guessers(), new BuiltInGuesser()]),
            ),
            $this->metadataEnrichers(),
        );

        if ($this->cache instanceof CacheItemPoolInterface) {
            $metadataFactory = new Psr6MetadataFactory($metadataFactory, $this->cache);
        }

        if ($this->cache instanceof CacheInterface) {
            $metadataFactory = new Psr16MetadataFactory($metadataFactory, $this->cache);
        }

        return new StackHydrator(
            $metadataFactory,
            $this->middlewares(),
            $this->defaultLazy,
            $this->mapperFactories(),
        );
    }

    public function defaultLazy(): bool
    {
        return $this->defaultLazy;
    }

    /** @return list<Middleware> */
    public function middlewares(): array
    {
        krsort($this->middlewares);

        return array_merge(...$this->middlewares);
    }

    /** @return list<Guesser> */
    public function guessers(): array
    {
        krsort($this->guessers);

        return array_merge(...$this->guessers);
    }

    /** @return list<MapperFactory> */
    public function mapperFactories(): array
    {
        krsort($this->mapperFactories);

        return array_merge(...$this->mapperFactories);
    }

    /** @return list<MetadataEnricher> */
    public function metadataEnrichers(): array
    {
        krsort($this->metadataEnrichers);

        return array_merge(...$this->metadataEnrichers);
    }
}
