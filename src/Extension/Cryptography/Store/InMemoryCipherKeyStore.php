<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Store;

use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;

use function array_key_last;

/** @experimental */
final class InMemoryCipherKeyStore implements CipherKeyStore
{
    /** @var array<string, CipherKey> */
    private array $indexById = [];

    /** @var array<string, array<string, CipherKey>> */
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

    public function get(string $id): CipherKey
    {
        return $this->indexById[$id] ?? throw CipherKeyNotExists::forKeyId($id);
    }

    public function store(CipherKey $key): void
    {
        $this->remove($key->id);

        $this->indexById[$key->id] = $key;
        $this->indexBySubjectId[$key->subjectId][$key->id] = $key;
    }

    public function remove(string $id): void
    {
        $key = $this->indexById[$id] ?? null;

        if (!$key) {
            return;
        }

        unset(
            $this->indexBySubjectId[$key->subjectId][$id],
            $this->indexById[$id],
        );
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
