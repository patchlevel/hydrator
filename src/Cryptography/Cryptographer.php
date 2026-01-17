<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use Patchlevel\Hydrator\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Cryptography\Cipher\EncryptionFailed;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyNotExists;

interface Cryptographer
{
    /** @throws EncryptionFailed */
    public function encrypt(string $subjectId, mixed $value): mixed;

    /**
     * @throws CipherKeyNotExists
     * @throws DecryptionFailed
     */
    public function decrypt(string $subjectId, mixed $encryptedData): mixed;

    public function supports(mixed $value): bool;
}
