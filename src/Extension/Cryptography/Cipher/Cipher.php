<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Cipher;

/** @experimental */
interface Cipher
{
    /** @throws EncryptionFailed */
    public function encrypt(CipherKey $key, mixed $data): EncryptedData;

    /** @throws DecryptionFailed */
    public function decrypt(CipherKey $key, EncryptedData $parameter): mixed;
}
