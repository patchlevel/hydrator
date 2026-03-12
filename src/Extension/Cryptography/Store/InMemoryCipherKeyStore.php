<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Store;

use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;

use function array_key_last;

final class InMemoryCipherKeyStore implements CipherKeyStore
{
    /** @var array<string, CipherKey> */
    private array $indexById = [];

    /** @var array<string, list<CipherKey>> */
    private array $indexBySubjectId = [];

    public function currentKeyFor(string $subjectId): CipherKey
    {
        if (!isset($this->indexBySubjectId[$subjectId])) {
            throw CipherKeyNotExists::forSubjectId($subjectId);
        }

        $lastKey = array_key_last($this->indexBySubjectId[$subjectId]);

        if ($lastKey === null) {
            throw CipherKeyNotExists::forSubjectId($subjectId);
        }

        return $this->indexBySubjectId[$subjectId][$lastKey];
    }

    public function get(string $keyId): CipherKey
    {
        return $this->indexById[$keyId] ?? throw CipherKeyNotExists::forKeyId($keyId);
    }

    public function store(string $id, CipherKey $key): void
    {
        $this->indexById[$id] = $key;

        if (!isset($this->indexBySubjectId[$key->subjectId])) {
            $this->indexBySubjectId[$key->subjectId] = [];
        }

        $this->indexBySubjectId[$key->subjectId][] = $key;
    }

    public function remove(string $id): void
    {
        unset($this->indexById[$id]);

        foreach ($this->indexBySubjectId as $subjectId => $keys) {
            $filtered = [];

            foreach ($keys as $key) {
                if ($key->id === $id) {
                    continue;
                }

                $filtered[] = $key;
            }

            if ($filtered === []) {
                unset($this->indexBySubjectId[$subjectId]);
            } else {
                $this->indexBySubjectId[$subjectId] = $filtered;
            }
        }
    }

    public function clear(): void
    {
        $this->indexById = [];
        $this->indexBySubjectId = [];
    }

    public function removeWithSubjectId(string $subjectId): void
    {
        if (!isset($this->indexBySubjectId[$subjectId])) {
            return;
        }

        foreach ($this->indexBySubjectId[$subjectId] as $key) {
            unset($this->indexById[$key->id]);
        }

        unset($this->indexBySubjectId[$subjectId]);
    }
}
