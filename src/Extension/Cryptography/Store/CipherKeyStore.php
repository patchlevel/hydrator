<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Store;

use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;

interface CipherKeyStore
{
    /** @throws CipherKeyNotExists */
    public function currentKeyFor(string $subjectId): CipherKey;

    /** @throws CipherKeyNotExists */
    public function get(string $keyId): CipherKey;

    public function store(string $id, CipherKey $key): void;

    public function remove(string $id): void;

    public function removeWithSubjectId(string $subjectId): void;
}
