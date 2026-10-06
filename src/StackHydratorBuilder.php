<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Guesser\ChainGuesser;
use Patchlevel\Hydrator\Guesser\Guesser;
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
use function ksort;

final class StackHydratorBuilder
{
    private bool $defaultLazy = false;

    /** @var array<int, list<Middleware>> */
    private array $middlewares = [];

    /** @var array<int, list<MetadataEnricher>> */
    private array $metadataEnrichers = [];

    /** @var array<int, list<Guesser>> */
    private array $guessers = [];

    /** @var array<int, list<HydratorDecorator>> */
    private array $decorators = [];

    private CacheItemPoolInterface|CacheInterface|null $cache = null;

    /** @return $this */
    public function addMiddleware(Middleware $middleware, int $priority = Extension::PRIORITY_BEFORE_TRANSFORM): static
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

    /** @return $this */
    public function addGuesser(Guesser $guesser, int $priority = 0): static
    {
        $this->guessers[$priority][] = $guesser;

        return $this;
    }

    /**
     * Decorators with a higher priority are wrapped around the ones with a lower priority.
     *
     * @return $this
     */
    public function addDecorator(HydratorDecorator $decorator, int $priority = 0): static
    {
        $this->decorators[$priority][] = $decorator;

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

    /** Builds the hydrator with all registered decorators. */
    public function buildHydrator(): Hydrator
    {
        $stack = $this->buildStackHydrator();
        $hydrator = $stack;

        foreach ($this->decorators() as $decorator) {
            $hydrator = $decorator->decorate($hydrator, $stack);
        }

        return $hydrator;
    }

    /**
     * Builds the plain stack hydrator. Fails if decorators are registered, since they can not be applied here.
     *
     * @deprecated use buildHydrator() instead, which also applies the decorators
     *
     * @throws DecoratorsNotApplied
     */
    public function build(): StackHydrator
    {
        if ($this->decorators !== []) {
            throw new DecoratorsNotApplied();
        }

        return $this->buildStackHydrator();
    }

    private function buildStackHydrator(): StackHydrator
    {
        $metadataFactory = new EnrichingMetadataFactory(
            new AttributeMetadataFactory(
                guesser: new ChainGuesser($this->guessers()),
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

    /** @return list<MetadataEnricher> */
    public function metadataEnrichers(): array
    {
        krsort($this->metadataEnrichers);

        return array_merge(...$this->metadataEnrichers);
    }

    /** @return list<HydratorDecorator> in the order they are applied, the innermost first */
    public function decorators(): array
    {
        ksort($this->decorators);

        return array_merge(...$this->decorators);
    }
}
