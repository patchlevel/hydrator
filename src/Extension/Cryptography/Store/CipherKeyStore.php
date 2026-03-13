<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Store;

use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;

/** @experimental */
interface CipherKeyStore
{
    /** @throws CipherKeyNotExists */
    public function currentKeyFor(string $subjectId): CipherKey;

    /** @throws CipherKeyNotExists */
    public function get(string $id): CipherKey;

    public function store(CipherKey $key): void;

    public function remove(string $id): void;

    public function removeWithSubjectId(string $subjectId): void;
}
