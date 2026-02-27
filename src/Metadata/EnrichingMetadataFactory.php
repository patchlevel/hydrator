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

    /**
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     *
     * @throws ClassNotFound if the class does not exist.
     *
     * @template T of object
     */
    public function metadata(string $class): ClassMetadata
    {
        $metadata = $this->factory->metadata($class);

        foreach ($this->enrichers as $enricher) {
            $enricher->enrich($metadata);
        }

        return $metadata;
    }
}
