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
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Stringable;

use function array_key_exists;
use function assert;
use function is_array;
use function is_int;
use function is_string;

final class CryptographyMiddleware implements Middleware
{
    private const DEFAULT_ENCRYPTED_FIELD_NAME_PREFIX = '!';

    public function __construct(
        private readonly Cipher $cipher,
        private readonly CipherKeyStore $cipherKeyStore,
        private readonly CipherKeyFactory $cipherKeyFactory,
        private readonly string|null $encryptedFieldNamePrefix = self::DEFAULT_ENCRYPTED_FIELD_NAME_PREFIX,
    ) {
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Stack $stack): object
    {
        $subjectIds = $context[SubjectIds::class] ?? new SubjectIds();

        assert($subjectIds instanceof SubjectIds);

        $mapping = $metadata->extras[SubjectIdFieldMapping::class] ?? null;

        if ($mapping instanceof SubjectIdFieldMapping) {
            $subjectIds = $this->resolveSubjectIds($metadata, $mapping, $data)
                ->merge($subjectIds);
        }

        $context[SubjectIds::class] = $subjectIds;

        foreach ($metadata->properties as $propertyMetadata) {
            $sensitiveDataInfo = $propertyMetadata->extras[SensitiveDataInfo::class] ?? null;

            if (!$sensitiveDataInfo instanceof SensitiveDataInfo) {
                continue;
            }

            $subjectId = $subjectIds->get($sensitiveDataInfo->subjectIdName);

            try {
                $cipherKey = $this->cipherKeyStore->get($subjectId);
            } catch (CipherKeyNotExists) {
                $cipherKey = null;
            }

            if (
                $this->encryptedFieldNamePrefix && array_key_exists(
                    $this->encryptedFieldNamePrefix . $propertyMetadata->fieldName,
                    $data,
                )
            ) {
                $rawData = $data[$this->encryptedFieldNamePrefix . $propertyMetadata->fieldName];
                unset($data[$this->encryptedFieldNamePrefix . $propertyMetadata->fieldName]);
            } elseif (!$this->encryptedFieldNamePrefix) {
                $rawData = $data[$propertyMetadata->fieldName];
            } else {
                continue;
            }

            if (!is_string($rawData)) {
                $data[$propertyMetadata->fieldName] = $rawData;

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

        return $stack->next()->hydrate(
            $metadata,
            $data,
            $context,
            $stack,
        );
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param T                    $object
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     *
     * @template T of object
     */
    public function extract(ClassMetadata $metadata, object $object, array $context, Stack $stack): array
    {
        $subjectIds = $context[SubjectIds::class] ?? new SubjectIds();

        assert($subjectIds instanceof SubjectIds);

        $mapping = $metadata->extras[SubjectIdFieldMapping::class] ?? null;

        if ($mapping instanceof SubjectIdFieldMapping) {
            $subjectIds = $this->resolveSubjectIds($metadata, $mapping, $object)
                ->merge($subjectIds);
        }

        $context[SubjectIds::class] = $subjectIds;

        $data = $stack->next()->extract($metadata, $object, $context, $stack);

        foreach ($metadata->properties as $propertyMetadata) {
            $sensitiveDataInfo = $propertyMetadata->extras[SensitiveDataInfo::class] ?? null;

            if (!$sensitiveDataInfo instanceof SensitiveDataInfo) {
                continue;
            }

            $subjectId = $subjectIds->get($sensitiveDataInfo->subjectIdName);

            try {
                $cipherKey = $this->cipherKeyStore->get($subjectId);
            } catch (CipherKeyNotExists) {
                $cipherKey = ($this->cipherKeyFactory)();
                $this->cipherKeyStore->store($subjectId, $cipherKey);
            }

            $targetFieldName = $this->encryptedFieldNamePrefix
                ? $this->encryptedFieldNamePrefix . $propertyMetadata->fieldName
                : $propertyMetadata->fieldName;

            $data[$targetFieldName] = $this->cipher->encrypt(
                $cipherKey,
                $data[$propertyMetadata->fieldName],
            );

            if (!$this->encryptedFieldNamePrefix) {
                continue;
            }

            unset($data[$propertyMetadata->fieldName]);
        }

        return $data;
    }

    /** @param array<string, mixed>|object $data */
    private function resolveSubjectIds(
        ClassMetadata $metadata,
        SubjectIdFieldMapping $mapping,
        array|object $data,
    ): SubjectIds {
        $result = [];

        foreach ($mapping->nameToField as $name => $fieldName) {
            if (is_array($data)) {
                if (!array_key_exists($fieldName, $data)) {
                    throw new MissingSubjectIdField($metadata->className, $fieldName);
                }

                $subjectId = $data[$fieldName];
            } else {
                $property = $metadata->propertyForField($fieldName);

                if ($property->normalizer) {
                    $subjectId = $property->normalizer->normalize($property->getValue($data));
                } else {
                    $subjectId = $property->getValue($data);
                }
            }

            if (is_int($subjectId)) {
                $subjectId = (string)$subjectId;
            }

            if ($subjectId instanceof Stringable) {
                $subjectId = $subjectId->__toString();
            }

            if (!is_string($subjectId)) {
                throw new UnsupportedSubjectId($metadata->className, $fieldName, $subjectId);
            }

            $result[$name] = $subjectId;
        }

        return new SubjectIds($result);
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
        string|null $encryptedFieldNamePrefix = self::DEFAULT_ENCRYPTED_FIELD_NAME_PREFIX,
    ): static {
        return new self(
            new OpensslCipher(),
            $cryptoStore,
            new OpensslCipherKeyFactory($method),
            $encryptedFieldNamePrefix,
        );
    }
}
