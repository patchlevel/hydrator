<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use Patchlevel\Hydrator\Cryptography\Cipher\Cipher;
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\Hydrator\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Cryptography\Cipher\OpensslCipher;
use Patchlevel\Hydrator\Cryptography\Cipher\OpensslCipherKeyFactory;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;
use Stringable;

use function array_key_exists;
use function is_int;
use function is_string;

final class PersonalDataPayloadCryptographer implements PayloadCryptographer
{
    public function __construct(
        private readonly CipherKeyStore $cipherKeyStore,
        private readonly CipherKeyFactory $cipherKeyFactory,
        private readonly Cipher $cipher,
        private readonly bool $useEncryptedFieldName = false,
        private readonly bool $fallbackToFieldName = false,
        private readonly bool $encryptNull = true,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function encrypt(ClassMetadata $metadata, array $data): array
    {
        $subjectId = $this->subjectId($metadata, $data);

        if ($subjectId === null) {
            return $data;
        }

        try {
            $cipherKey = $this->cipherKeyStore->get($subjectId);
        } catch (CipherKeyNotExists) {
            $cipherKey = ($this->cipherKeyFactory)();
            $this->cipherKeyStore->store($subjectId, $cipherKey);
        }

        foreach ($metadata->properties() as $propertyMetadata) {
            if (!$propertyMetadata->isPersonalData()) {
                continue;
            }

            $value = $data[$propertyMetadata->fieldName()] ?? null;

            if (!$this->encryptNull && $value === null) {
                continue;
            }

            $targetFieldName = $this->useEncryptedFieldName
                ? $propertyMetadata->encryptedFieldName()
                : $propertyMetadata->fieldName();

            $data[$targetFieldName] = $this->cipher->encrypt(
                $cipherKey,
                $value,
            );

            if (!$this->useEncryptedFieldName) {
                continue;
            }

            unset($data[$propertyMetadata->fieldName()]);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function decrypt(ClassMetadata $metadata, array $data): array
    {
        $subjectId = $this->subjectId($metadata, $data);

        if ($subjectId === null) {
            return $data;
        }

        try {
            $cipherKey = $this->cipherKeyStore->get($subjectId);
        } catch (CipherKeyNotExists) {
            $cipherKey = null;
        }

        foreach ($metadata->properties() as $propertyMetadata) {
            if (!$propertyMetadata->isPersonalData()) {
                continue;
            }

            if ($this->useEncryptedFieldName && array_key_exists($propertyMetadata->encryptedFieldName(), $data)) {
                $rawData = $data[$propertyMetadata->encryptedFieldName()];
                unset($data[$propertyMetadata->encryptedFieldName()]);
            } elseif (!$this->useEncryptedFieldName || $this->fallbackToFieldName) {
                $rawData = $data[$propertyMetadata->fieldName()];
            } else {
                continue;
            }

            if (!is_string($rawData)) {
                continue;
            }

            if (!$cipherKey) {
                $data[$propertyMetadata->fieldName()] = $this->fallback($propertyMetadata, $subjectId, $rawData);
                continue;
            }

            try {
                $data[$propertyMetadata->fieldName()] = $this->cipher->decrypt(
                    $cipherKey,
                    $rawData,
                );
            } catch (DecryptionFailed) {
                $data[$propertyMetadata->fieldName()] = $this->fallback($propertyMetadata, $subjectId, $rawData);
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function subjectId(ClassMetadata $metadata, array $data): string|null
    {
        $fieldName = $metadata->dataSubjectIdField();

        if ($fieldName === null) {
            return null;
        }

        if (!array_key_exists($fieldName, $data)) {
            throw new MissingSubjectId($metadata->className(), $fieldName);
        }

        $subjectId = $data[$fieldName];

        if (is_int($subjectId)) {
            $subjectId = (string)$subjectId;
        }

        if ($subjectId instanceof Stringable) {
            $subjectId = $subjectId->__toString();
        }

        if (!is_string($subjectId)) {
            throw new UnsupportedSubjectId($metadata->className(), $fieldName, $subjectId);
        }

        return $subjectId;
    }

    private function fallback(PropertyMetadata $propertyMetadata, string $subjectId, mixed $rawData): mixed
    {
        $callback = $propertyMetadata->personalDataFallbackCallback();

        if (!$callback) {
            return $propertyMetadata->personalDataFallback();
        }

        return $callback($subjectId, $rawData);
    }

    /** @param non-empty-string $method */
    public static function createWithOpenssl(
        CipherKeyStore $cryptoStore,
        string $method = OpensslCipherKeyFactory::DEFAULT_METHOD,
        bool $useEncryptedFieldName = false,
        bool $fallbackToFieldName = false,
    ): static {
        return new self(
            $cryptoStore,
            new OpensslCipherKeyFactory($method),
            new OpensslCipher(),
            $useEncryptedFieldName,
            $fallbackToFieldName,
        );
    }

    /** @param non-empty-string $method */
    public static function createWithDefaultSettings(
        CipherKeyStore $cryptoStore,
        string $method = OpensslCipherKeyFactory::DEFAULT_METHOD,
    ): static {
        return new self(
            $cryptoStore,
            new OpensslCipherKeyFactory($method),
            new OpensslCipher(),
            true,
        );
    }
}
