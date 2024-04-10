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

use function array_key_exists;
use function is_int;
use function is_string;

final class PersonalDataPayloadCryptographer implements PayloadCryptographer
{
    public function __construct(
        private readonly CipherKeyStore $cipherKeyStore,
        private readonly CipherKeyFactory $cipherKeyFactory,
        private readonly Cipher $cipher,
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

            $data[$propertyMetadata->fieldName()] = $this->cipher->encrypt(
                $cipherKey,
                $data[$propertyMetadata->fieldName()],
            );
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

            if (!$cipherKey) {
                $data[$propertyMetadata->fieldName()] = $propertyMetadata->personalDataFallback();
                continue;
            }

            try {
                $data[$propertyMetadata->fieldName()] = $this->cipher->decrypt(
                    $cipherKey,
                    $data[$propertyMetadata->fieldName()],
                );
            } catch (DecryptionFailed) {
                $data[$propertyMetadata->fieldName()] = $propertyMetadata->personalDataFallback();
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

        if (!is_string($subjectId)) {
            throw new UnsupportedSubjectId($metadata->className(), $fieldName, $subjectId);
        }

        return $subjectId;
    }

    /** @param non-empty-string $method */
    public static function createWithOpenssl(
        CipherKeyStore $cryptoStore,
        string $method = OpensslCipherKeyFactory::DEFAULT_METHOD,
    ): static {
        return new self(
            $cryptoStore,
            new OpensslCipherKeyFactory($method),
            new OpensslCipher(),
        );
    }
}
