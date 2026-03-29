<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography;

use Closure;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Normalizer\NormalizerWithContext;
use Stringable;

use function array_key_exists;
use function assert;
use function is_array;
use function is_int;
use function is_string;

/** @experimental */
final class CryptographyMiddleware implements Middleware
{
    public function __construct(
        private readonly Cryptographer $cryptographer,
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
        $context[SubjectIds::class] = $subjectIds = $this->resolveSubjectIds($metadata, $data, $context);

        if ($context[LegacyCryptographyDecryptMiddleware::class] ?? false) {
            unset($context[LegacyCryptographyDecryptMiddleware::class]);

            return $stack->next()->hydrate($metadata, $data, $context, $stack);
        }

        foreach ($metadata->properties as $propertyMetadata) {
            $info = $propertyMetadata->extras[SensitiveDataInfo::class] ?? null;

            if (!$info instanceof SensitiveDataInfo) {
                continue;
            }

            $value = $data[$propertyMetadata->fieldName] ?? null;

            if ($value === null) {
                continue;
            }

            if (!$this->cryptographer->supports($value)) {
                continue;
            }

            $subjectId = $subjectIds->get($info->subjectIdName);

            try {
                $data[$propertyMetadata->fieldName] = $this->cryptographer->decrypt($subjectId, $value);
            } catch (DecryptionFailed | CipherKeyNotExists) {
                $fallback = $info->fallback instanceof Closure
                    ? ($info->fallback)($subjectId)
                    : $info->fallback;

                if ($propertyMetadata->normalizer) {
                    if ($propertyMetadata->normalizer instanceof NormalizerWithContext) {
                        $fallback = $propertyMetadata->normalizer->normalize($fallback, $context);
                    } else {
                        $fallback = $propertyMetadata->normalizer->normalize($fallback);
                    }
                }

                $data[$propertyMetadata->fieldName] = $fallback;
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
        $context[SubjectIds::class] = $subjectIds = $this->resolveSubjectIds($metadata, $object, $context);

        $data = $stack->next()->extract($metadata, $object, $context, $stack);

        foreach ($metadata->properties as $propertyMetadata) {
            $info = $propertyMetadata->extras[SensitiveDataInfo::class] ?? null;

            if (!$info instanceof SensitiveDataInfo) {
                continue;
            }

            $value = $data[$propertyMetadata->fieldName] ?? null;

            if ($value === null) {
                continue;
            }

            $data[$propertyMetadata->fieldName] = $this->cryptographer->encrypt(
                $subjectIds->get($info->subjectIdName),
                $value,
            );
        }

        return $data;
    }

    /**
     * @param array<string, mixed>|object $data
     * @param array<string, mixed>        $context
     */
    private function resolveSubjectIds(
        ClassMetadata $metadata,
        array|object $data,
        array $context,
    ): SubjectIds {
        $subjectIds = $context[SubjectIds::class] ?? new SubjectIds();
        assert($subjectIds instanceof SubjectIds);

        $mapping = $metadata->extras[SubjectIdFieldMapping::class] ?? null;

        if (!$mapping instanceof SubjectIdFieldMapping) {
            return $subjectIds;
        }

        $result = [];

        foreach ($mapping->nameToField as $name => $fieldName) {
            if (is_array($data)) {
                if (!array_key_exists($fieldName, $data)) {
                    throw new MissingSubjectIdForField($metadata->className, $fieldName);
                }

                $subjectId = $data[$fieldName];
            } else {
                $property = $metadata->propertyForField($fieldName);
                $subjectId = $property->getValue($data);

                if ($property->normalizer) {
                    if ($property->normalizer instanceof NormalizerWithContext) {
                        $subjectId = $property->normalizer->normalize($subjectId, $context);
                    } else {
                        $subjectId = $property->normalizer->normalize($subjectId);
                    }
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

        return $subjectIds->merge(new SubjectIds($result));
    }
}
