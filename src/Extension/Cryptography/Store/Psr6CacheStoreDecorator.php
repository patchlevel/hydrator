<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Store;

use DateInterval;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;
use Psr\Cache\CacheItemPoolInterface;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function is_string;

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
        $entry = $this->fetch(CacheKey::forSubjectId($subjectId));

        if ($entry instanceof CipherKey && $entry->subjectId === $subjectId) {
            return $entry;
        }

        $entry = $this->cipherKeyStore->currentKeyFor($subjectId);

        $this->save(CacheKey::forSubjectId($subjectId), $entry);

        return $entry;
    }

    public function get(string $id): CipherKey
    {
        $entry = $this->fetch(CacheKey::forId($id));

        if ($entry instanceof CipherKey && $entry->id === $id) {
            return $entry;
        }

        $entry = $this->cipherKeyStore->get($id);

        $this->save(CacheKey::forId($id), $entry);
        $this->rememberKeyId($entry);

        return $entry;
    }

    public function store(CipherKey $key): void
    {
        $this->cipherKeyStore->store($key);
    }

    public function remove(string $id): void
    {
        try {
            $subjectId = $this->get($id)->subjectId;
        } catch (CipherKeyNotExists) {
            $subjectId = null;
        }

        $this->cipherKeyStore->remove($id);

        $this->cache->deleteItem(CacheKey::forId($id));

        if ($subjectId === null) {
            return;
        }

        $this->cache->deleteItem(CacheKey::forSubjectId($subjectId));
    }

    public function removeWithSubjectId(string $subjectId): void
    {
        $this->cipherKeyStore->removeWithSubjectId($subjectId);

        $this->cache->deleteItems([
            CacheKey::forSubjectId($subjectId),
            CacheKey::forSubjectKeyIds($subjectId),
            ...array_map(CacheKey::forId(...), $this->keyIds($subjectId)),
        ]);
    }

    private function fetch(string $cacheKey): mixed
    {
        $item = $this->cache->getItem($cacheKey);

        if (!$item->isHit()) {
            return null;
        }

        return $item->get();
    }

    private function save(string $cacheKey, mixed $value): void
    {
        $item = $this->cache->getItem($cacheKey);
        $item->set($value);
        $item->expiresAfter($this->expiresAfter);

        $this->cache->save($item);
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

        $this->save(CacheKey::forSubjectKeyIds($key->subjectId), $keyIds);
    }

    /** @return list<string> */
    private function keyIds(string $subjectId): array
    {
        $keyIds = $this->fetch(CacheKey::forSubjectKeyIds($subjectId));

        if (!is_array($keyIds)) {
            return [];
        }

        return array_values(array_filter($keyIds, is_string(...)));
    }
}
