<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

interface MetadataEnricher
{
    public function enrich(ClassMetadata $classMetadata): void;
}
