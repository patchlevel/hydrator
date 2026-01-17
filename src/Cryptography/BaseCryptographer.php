<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use Patchlevel\Hydrator\Cryptography\Cipher\Cipher;
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\Hydrator\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Cryptography\Cipher\EncryptionFailed;
use Patchlevel\Hydrator\Cryptography\Cipher\OpensslCipher;
use Patchlevel\Hydrator\Cryptography\Cipher\OpensslCipherKeyFactory;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore;

use function array_key_exists;
use function is_array;

/**
 * @phpstan-type EncryptedDataV1 array{
 *     __enc: 'v1',
 *     data: non-empty-string,
 *     method?: non-empty-string,
 *     iv?: non-empty-string,
 * }
 */
final class BaseCryptographer implements Cryptographer
{
    public function __construct(
        private readonly Cipher $cipher,
        private readonly CipherKeyStore $cipherKeyStore,
        private readonly CipherKeyFactory $cipherKeyFactory,
    ) {
    }

    /**
     * @return EncryptedDataV1
     *
     * @throws EncryptionFailed
     */
    public function encrypt(string $subjectId, mixed $value): array
    {
        try {
            $cipherKey = $this->cipherKeyStore->get($subjectId);
        } catch (CipherKeyNotExists) {
            $cipherKey = ($this->cipherKeyFactory)();
            $this->cipherKeyStore->store($subjectId, $cipherKey);
        }

        return [
            '__enc' => 'v1',
            'data' => $this->cipher->encrypt($cipherKey, $value),
            'method' => $cipherKey->method,
            'iv' => $cipherKey->iv,
        ];
    }

    /**
     * @param EncryptedDataV1 $encryptedData
     *
     * @throws CipherKeyNotExists
     * @throws DecryptionFailed
     */
    public function decrypt(string $subjectId, mixed $encryptedData): mixed
    {
        $cipherKey = $this->cipherKeyStore->get($subjectId);

        return $this->cipher->decrypt(
            new CipherKey(
                $cipherKey->key,
                $encryptedData['method'] ?? $cipherKey->method,
                $encryptedData['iv'] ?? $cipherKey->iv,
            ),
            $encryptedData['data'],
        );
    }

    public function supports(mixed $value): bool
    {
        return is_array($value) && array_key_exists('__enc', $value) && $value['__enc'] === 'v1';
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
