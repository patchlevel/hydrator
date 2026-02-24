<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

final readonly class EnrichingMetadataFactory implements MetadataFactory
{
    /** @param iterable<MetadataEnricher> $enrichers */
    public function __construct(
        private MetadataFactory $factory,
        private iterable $enrichers,
    ) {
    }

    public function metadata(string $class): ClassMetadata
    {
        $metadata = $this->factory->metadata($class);

        $enriched = false;
        foreach ($this->enrichers as $enricher) {
            $enricher->enrich($metadata);
            $enriched = true;
        }

        if ($enriched) {
            $metadata->updateProperties(array_values($metadata->properties));
        }

        return $metadata;
    }
}
