<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography;

use Patchlevel\Hydrator\Extension\Cryptography\Cipher\Cipher;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\EncryptedData;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\EncryptionFailed;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\OpensslCipher;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\OpensslCipherKeyFactory;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyStore;

use function is_array;

/**
 * @experimental
 * @phpstan-type EncryptedDataArray array{
 *   v: 1,
 *   a: non-empty-string,
 *   k: non-empty-string,
 *   n?: non-empty-string,      // base64
 *   d: non-empty-string,        // base64 ciphertext
 *   t?: non-empty-string,       // base64 (for AEAD)
 * }
 */
final class BaseCryptographer implements Cryptographer
{
    private const VERSION_KEY = 'v';
    private const METHOD_KEY = 'a';
    private const KEY_ID_KEY = 'k';
    private const NONCE_KEY = 'n';
    private const DATA_KEY = 'd';
    private const TAG_KEY = 't';

    public function __construct(
        private readonly Cipher $cipher,
        private readonly CipherKeyStore $cipherKeyStore,
        private readonly CipherKeyFactory $cipherKeyFactory,
    ) {
    }

    /**
     * @return EncryptedDataArray
     *
     * @throws EncryptionFailed
     */
    public function encrypt(string $subjectId, mixed $value): array
    {
        try {
            $cipherKey = $this->cipherKeyStore->currentKeyFor($subjectId);
        } catch (CipherKeyNotExists) {
            $cipherKey = ($this->cipherKeyFactory)($subjectId);
            $this->cipherKeyStore->store($cipherKey->id, $cipherKey);
        }

        $parameter = $this->cipher->encrypt($cipherKey, $value);

        $result = [
            self::VERSION_KEY => 1,
            self::METHOD_KEY => $parameter->method,
            self::KEY_ID_KEY => $cipherKey->id,
            self::DATA_KEY => $parameter->data,
        ];

        if ($parameter->nonce !== null) {
            $result[self::NONCE_KEY] = $parameter->nonce;
        }

        if ($parameter->tag !== null) {
            $result[self::TAG_KEY] = $parameter->tag;
        }

        return $result;
    }

    /**
     * @param EncryptedDataArray $encryptedData
     *
     * @throws CipherKeyNotExists
     * @throws DecryptionFailed
     */
    public function decrypt(string $subjectId, mixed $encryptedData): mixed
    {
        $keyId = $encryptedData[self::KEY_ID_KEY] ?? null;

        if ($keyId === null) {
            throw DecryptionFailed::missingKeyId();
        }

        $cipherKey = $this->cipherKeyStore->get($keyId);

        return $this->cipher->decrypt(
            $cipherKey,
            new EncryptedData(
                $encryptedData[self::DATA_KEY],
                $encryptedData[self::METHOD_KEY],
                $encryptedData[self::NONCE_KEY] ?? null,
                $encryptedData[self::TAG_KEY] ?? null,
            ),
        );
    }

    public function supports(mixed $value): bool
    {
        return is_array($value)
            && isset($value[self::VERSION_KEY], $value[self::METHOD_KEY], $value[self::KEY_ID_KEY], $value[self::DATA_KEY])
            && $value[self::VERSION_KEY] === 1;
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
