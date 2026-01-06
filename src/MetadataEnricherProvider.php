<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Metadata\MetadataEnricher;

interface MetadataEnricherProvider
{
    /** @return iterable<MetadataEnricher|array{0: MetadataEnricher, 1?: int}> */
    public function metadataEnrichers(): iterable;
}
