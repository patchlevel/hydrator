<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\Middleware\Middleware;

final class DummyExtension extends Extension
{
    /**
     * @param iterable<Middleware>       $middlewares
     * @param iterable<MetadataEnricher> $metadataEnrichers
     * @param iterable<Guesser>          $guessers
     */
    public function __construct(
        private iterable $middlewares = [],
        private iterable $metadataEnrichers = [],
        private iterable $guessers = [],
    ) {
    }

    /** @return iterable<Guesser> */
    public function guesser(): iterable
    {
        return $this->guessers;
    }

    /** @return iterable<MetadataEnricher> */
    public function metadataEnrichers(): iterable
    {
        return $this->metadataEnrichers;
    }

    /** @return iterable<Middleware> */
    public function middlewares(): iterable
    {
        return $this->middlewares;
    }
}
