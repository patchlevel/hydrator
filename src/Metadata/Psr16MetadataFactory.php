<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use Psr\SimpleCache\CacheInterface;

final class Psr16MetadataFactory implements MetadataFactory
{
    public function __construct(
        private readonly MetadataFactory $metadataFactory,
        private readonly CacheInterface $cache,
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
        /** @var ?ClassMetadata<T> $metadata */
        $metadata = $this->cache->get($class);

        if ($metadata !== null) {
            return $metadata;
        }

        $metadata = $this->metadataFactory->metadata($class);

        $this->cache->set($class, $metadata);

        return $metadata;
    }
}
