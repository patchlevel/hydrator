<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Store;

use DateInterval;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;
use Psr\SimpleCache\CacheInterface;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function is_string;

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
        $entry = $this->cache->get(CacheKey::forSubjectId($subjectId));

        if ($entry instanceof CipherKey && $entry->subjectId === $subjectId) {
            return $entry;
        }

        $entry = $this->cipherKeyStore->currentKeyFor($subjectId);

        $this->cache->set(CacheKey::forSubjectId($subjectId), $entry, $this->ttl);
        $this->rememberKeyId($entry);

        return $entry;
    }

    public function get(string $id): CipherKey
    {
        $entry = $this->cache->get(CacheKey::forId($id));

        if ($entry instanceof CipherKey && $entry->id === $id) {
            return $entry;
        }

        $entry = $this->cipherKeyStore->get($id);

        $this->cache->set(CacheKey::forId($id), $entry, $this->ttl);
        $this->rememberKeyId($entry);

        return $entry;
    }

    public function store(CipherKey $key): void
    {
        $this->cipherKeyStore->store($key);

        $this->cache->set(CacheKey::forId($key->id), $key, $this->ttl);
        $this->cache->set(CacheKey::forSubjectId($key->subjectId), $key, $this->ttl);
        $this->rememberKeyId($key);
    }

    public function remove(string $id): void
    {
        try {
            $subjectId = $this->get($id)->subjectId;
        } catch (CipherKeyNotExists) {
            $subjectId = null;
        }

        $this->cipherKeyStore->remove($id);

        $this->cache->delete(CacheKey::forId($id));

        if ($subjectId === null) {
            return;
        }

        $this->cache->delete(CacheKey::forSubjectId($subjectId));
    }

    public function removeWithSubjectId(string $subjectId): void
    {
        $this->cipherKeyStore->removeWithSubjectId($subjectId);

        $this->cache->deleteMultiple([
            CacheKey::forSubjectId($subjectId),
            CacheKey::forSubjectKeyIds($subjectId),
            ...array_map(CacheKey::forId(...), $this->keyIds($subjectId)),
        ]);
    }

    /**
     * Remember which key ids of a subject are cached, so that all of them can be evicted on removal.
     * The list is saved after the key itself, so it never expires before one of its keys.
     */
    private function rememberKeyId(CipherKey $key): void
    {
        $keyIds = $this->keyIds($key->subjectId);

        if (!in_array($key->id, $keyIds, true)) {
            $keyIds[] = $key->id;
        }

        $this->cache->set(CacheKey::forSubjectKeyIds($key->subjectId), $keyIds, $this->ttl);
    }

    /** @return list<string> */
    private function keyIds(string $subjectId): array
    {
        $keyIds = $this->cache->get(CacheKey::forSubjectKeyIds($subjectId));

        if (!is_array($keyIds)) {
            return [];
        }

        return array_values(array_filter($keyIds, is_string(...)));
    }
}
