<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Store;

use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;

interface CipherKeyStore
{
    /** @throws CipherKeyNotExists */
    public function get(string $id): CipherKey;

    public function store(string $id, CipherKey $key): void;

    public function remove(string $id): void;
}
