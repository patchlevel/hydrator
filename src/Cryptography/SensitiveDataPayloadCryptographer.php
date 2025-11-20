<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use Closure;
use Patchlevel\Hydrator\Cryptography\Cipher\Cipher;
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\Hydrator\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Cryptography\Cipher\OpensslCipher;
use Patchlevel\Hydrator\Cryptography\Cipher\OpensslCipherKeyFactory;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Stringable;

use function array_key_exists;
use function is_int;
use function is_string;

final class SensitiveDataPayloadCryptographer implements PayloadCryptographer
{
    private const ENCRYPTED_PREFIX = '!';

    public function __construct(
        private readonly CipherKeyStore $cipherKeyStore,
        private readonly CipherKeyFactory $cipherKeyFactory,
        private readonly Cipher $cipher,
        private readonly bool $useEncryptedFieldName = false,
        private readonly bool $fallbackToFieldName = false,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function encrypt(ClassMetadata $metadata, array $data): array
    {
        $mapping = $metadata->extras[SubjectIdFieldMapping::class] ?? null;

        if (!$mapping instanceof SubjectIdFieldMapping) {
            return $data;
        }

        $subjectIds = $this->getSubjectIds($metadata, $mapping, $data);

        foreach ($metadata->properties as $propertyMetadata) {
            $sensitiveDataInfo = $propertyMetadata->extras[SensitiveDataInfo::class] ?? null;

            if (!$sensitiveDataInfo instanceof SensitiveDataInfo) {
                continue;
            }

            $subjectId = $subjectIds[$sensitiveDataInfo->subjectIdName] ?? null;

            if ($subjectId === null) {
                throw new MissingSubjectId($metadata->className(), $sensitiveDataInfo->subjectIdName);
            }

            try {
                $cipherKey = $this->cipherKeyStore->get($subjectId);
            } catch (CipherKeyNotExists) {
                $cipherKey = ($this->cipherKeyFactory)();
                $this->cipherKeyStore->store($subjectId, $cipherKey);
            }

            $targetFieldName = $this->useEncryptedFieldName
                ? self::ENCRYPTED_PREFIX . $propertyMetadata->fieldName
                : $propertyMetadata->fieldName;

            $data[$targetFieldName] = $this->cipher->encrypt(
                $cipherKey,
                $data[$propertyMetadata->fieldName],
            );

            if (!$this->useEncryptedFieldName) {
                continue;
            }

            unset($data[$propertyMetadata->fieldName]);
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
        $mapping = $metadata->extras[SubjectIdFieldMapping::class] ?? null;

        if (!$mapping instanceof SubjectIdFieldMapping) {
            return $data;
        }

        $subjectIds = $this->getSubjectIds($metadata, $mapping, $data);

        foreach ($metadata->properties as $propertyMetadata) {
            $sensitiveDataInfo = $propertyMetadata->extras[SensitiveDataInfo::class] ?? null;

            if (!$sensitiveDataInfo instanceof SensitiveDataInfo) {
                continue;
            }

            $subjectId = $subjectIds[$sensitiveDataInfo->subjectIdName] ?? null;

            if ($subjectId === null) {
                throw new MissingSubjectId($metadata->className(), $sensitiveDataInfo->subjectIdName);
            }

            try {
                $cipherKey = $this->cipherKeyStore->get($subjectId);
            } catch (CipherKeyNotExists) {
                $cipherKey = null;
            }

            if ($this->useEncryptedFieldName && array_key_exists(self::ENCRYPTED_PREFIX . $propertyMetadata->fieldName, $data)) {
                $rawData = $data[self::ENCRYPTED_PREFIX . $propertyMetadata->fieldName];
                unset($data[self::ENCRYPTED_PREFIX . $propertyMetadata->fieldName]);
            } elseif (!$this->useEncryptedFieldName || $this->fallbackToFieldName) {
                $rawData = $data[$propertyMetadata->fieldName];
            } else {
                continue;
            }

            if (!$cipherKey) {
                $data[$propertyMetadata->fieldName] = $this->fallback($sensitiveDataInfo, $subjectId, $rawData);
                continue;
            }

            try {
                $data[$propertyMetadata->fieldName] = $this->cipher->decrypt(
                    $cipherKey,
                    $rawData,
                );
            } catch (DecryptionFailed) {
                $data[$propertyMetadata->fieldName] = $this->fallback($sensitiveDataInfo, $subjectId, $rawData);
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function getSubjectIds(ClassMetadata $metadata, SubjectIdFieldMapping $mapping, array $data): array
    {
        $result = [];

        foreach ($mapping->nameToField as $name => $fieldName) {
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

            $result[$name] = $subjectId;
        }

        return $result;
    }

    private function fallback(SensitiveDataInfo $sensitiveDataInfo, string $subjectId, mixed $rawData): mixed
    {
        if ($sensitiveDataInfo->fallback instanceof Closure) {
            return ($sensitiveDataInfo->fallback)($subjectId, $rawData);
        }

        return $sensitiveDataInfo->fallback;
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
