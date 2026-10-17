<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use Psr\Cache\CacheItemPoolInterface;

final readonly class Psr6MetadataFactory implements MetadataFactory
{
    public function __construct(
        private MetadataFactory $metadataFactory,
        private CacheItemPoolInterface $cache,
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
        $item = $this->cache->getItem(CacheKey::forClass($class));

        if ($item->isHit()) {
            $data = $item->get();

            if ($data instanceof ClassMetadata && $data->className === $class) {
                return $data;
            }
        }

        $metadata = $this->metadataFactory->metadata($class);

        $item->set($metadata);
        $this->cache->save($item);

        return $metadata;
    }
}
