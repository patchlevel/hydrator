<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Store;

use DateInterval;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;
use Psr\SimpleCache\CacheInterface;

final readonly class Psr16CacheStoreDecorator implements CipherKeyStore
{
    public function __construct(
        private CipherKeyStore $cipherKeyStore,
        private CacheInterface $cache,
        private DateInterval|int|null $ttl = null,
    ) {
    }

    public function currentKeyFor(string $subjectId): CipherKey
    {
        $key = 'subjectId:' . $subjectId;
        $entry = $this->cache->get($key);

        if ($entry instanceof CipherKey) {
            return $entry;
        }

        $entry = $this->cipherKeyStore->currentKeyFor($subjectId);

        $this->cache->set($key, $entry, $this->ttl);

        return $entry;
    }

    public function get(string $id): CipherKey
    {
        $key = 'id:' . $id;
        $entry = $this->cache->get($key);

        if ($entry instanceof CipherKey) {
            return $entry;
        }

        $entry = $this->cipherKeyStore->get($id);

        $this->cache->set($key, $entry, $this->ttl);

        return $entry;
    }

    public function store(CipherKey $key): void
    {
        $this->cipherKeyStore->store($key);

        $this->cache->set('id:' . $key->id, $key, $this->ttl);
        $this->cache->set('subjectId:' . $key->subjectId, $key, $this->ttl);
    }

    public function remove(string $id): void
    {
        $this->cipherKeyStore->remove($id);

        $this->cache->delete('id:' . $id);
    }

    public function removeWithSubjectId(string $subjectId): void
    {
        $this->cipherKeyStore->removeWithSubjectId($subjectId);

        $this->cache->delete('subjectId:' . $subjectId);
    }
}
