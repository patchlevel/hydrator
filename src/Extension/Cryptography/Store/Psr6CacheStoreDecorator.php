<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Store;

use DateInterval;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;
use Psr\Cache\CacheItemPoolInterface;

final readonly class Psr6CacheStoreDecorator implements CipherKeyStore
{
    public function __construct(
        private CipherKeyStore $cipherKeyStore,
        private CacheItemPoolInterface $cache,
        private DateInterval|int|null $expiresAfter = null,
    ) {
    }

    public function currentKeyFor(string $subjectId): CipherKey
    {
        $key = 'subjectId:' . $subjectId;
        $item = $this->cache->getItem($key);
        $entry = $item->get();

        if ($item->isHit() && $entry instanceof CipherKey) {
            return $entry;
        }

        $entry = $this->cipherKeyStore->currentKeyFor($subjectId);

        $item->set($entry);
        $item->expiresAfter($this->expiresAfter);
        $this->cache->save($item);

        return $entry;
    }

    public function get(string $id): CipherKey
    {
        $key = 'id:' . $id;
        $item = $this->cache->getItem($key);
        $entry = $item->get();

        if ($item->isHit() && $entry instanceof CipherKey) {
            return $entry;
        }

        $entry = $this->cipherKeyStore->get($id);

        $item->set($entry);
        $item->expiresAfter($this->expiresAfter);
        $this->cache->save($item);

        return $entry;
    }

    public function store(CipherKey $key): void
    {
        $this->cipherKeyStore->store($key);
    }

    public function remove(string $id): void
    {
        $this->cipherKeyStore->remove($id);

        $this->cache->deleteItem('id:' . $id);
    }

    public function removeWithSubjectId(string $subjectId): void
    {
        $this->cipherKeyStore->removeWithSubjectId($subjectId);

        $this->cache->deleteItem('subjectId:' . $subjectId);
    }
}
