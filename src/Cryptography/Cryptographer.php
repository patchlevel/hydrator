<?php

namespace Patchlevel\Hydrator\Cryptography;

use Patchlevel\Hydrator\Cryptography\Cipher\Cipher;
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\Hydrator\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Cryptography\Cipher\EncryptionFailed;
use Patchlevel\Hydrator\Cryptography\Cipher\OpensslCipher;
use Patchlevel\Hydrator\Cryptography\Cipher\OpensslCipherKeyFactory;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore;

final class Cryptographer
{
    public function __construct(
        private readonly Cipher $cipher,
        private readonly CipherKeyStore $cipherKeyStore,
        private readonly CipherKeyFactory $cipherKeyFactory,
    )
    {
    }

    /**
     * @throws EncryptionFailed
     */
    public function encrypt(string $subjectId, mixed $value): string
    {
        try {
            $cipherKey = $this->cipherKeyStore->get($subjectId);
        } catch (CipherKeyNotExists) {
            $cipherKey = ($this->cipherKeyFactory)();
            $this->cipherKeyStore->store($subjectId, $cipherKey);
        }

        return $this->cipher->encrypt($cipherKey, $value);
    }

    /**
     * @throws CipherKeyNotExists
     * @throws DecryptionFailed
     */
    public function decrypt(string $subjectId, string $value): mixed
    {
        $cipherKey = $this->cipherKeyStore->get($subjectId);

        return $this->cipher->decrypt($cipherKey, $value);
    }


    /** @param non-empty-string $method */
    public static function createWithOpenssl(
        CipherKeyStore $cryptoStore,
        string $method = OpensslCipherKeyFactory::DEFAULT_METHOD,
    ): static {
        return new self(
            new OpensslCipher(),
            $cryptoStore,
            new OpensslCipherKeyFactory($method),
        );
    }
}