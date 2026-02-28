<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography;

use Patchlevel\Hydrator\Extension\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\EncryptionFailed;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyNotExists;

/** @experimental */
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
