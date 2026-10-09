<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use Psr\SimpleCache\CacheInterface;

final readonly class Psr16MetadataFactory implements MetadataFactory
{
    public function __construct(
        private MetadataFactory $metadataFactory,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     *
     * @template T of object
     */
    public function metadata(string $class): ClassMetadata
    {
        $metadata = $this->cache->get(CacheKey::forClass($class));

        if ($metadata instanceof ClassMetadata && $metadata->className === $class) {
            return $metadata;
        }

        $metadata = $this->metadataFactory->metadata($class);

        $this->cache->set(CacheKey::forClass($class), $metadata);

        return $metadata;
    }
}
