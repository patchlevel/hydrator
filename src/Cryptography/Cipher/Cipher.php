<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography\Cipher;

interface Cipher
{
    /**
     * @return non-empty-string
     *
     * @throws EncryptionFailed
     */
    public function encrypt(CipherKey $key, mixed $data): string;

    /** @throws DecryptionFailed */
    public function decrypt(CipherKey $key, string $data): mixed;
}
