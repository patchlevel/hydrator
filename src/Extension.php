<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\Middleware\Middleware;

abstract class Extension
{
    /** @return iterable<Middleware|array{0: Middleware, 1?: int}> */
    public function middlewares(): iterable
    {
        return [];
    }

    /** @return iterable<MetadataEnricher|array{0: MetadataEnricher, 1?: int}> */
    public function metadataEnrichers(): iterable
    {
        return [];
    }

    /** @return iterable<Guesser|array{0: Guesser, 1?: int}> */
    public function guesser(): iterable
    {
        return [];
    }
}
