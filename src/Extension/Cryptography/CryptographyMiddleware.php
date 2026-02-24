<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography;

use Closure;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyNotExists;
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
        /** @var list<PropertyMetadata>|null $properties */
        $properties = $metadata->extras[SensitiveDataInfo::class . '::properties'] ?? null;

        if ($properties === null) {
            return $stack->next()->hydrate($metadata, $data, $context, $stack);
        }

        $context[SubjectIds::class] = $subjectIds = $this->resolveSubjectIds($metadata, $data, $context);
        $cryptographer = $this->cryptographer;

        foreach ($properties as $propertyMetadata) {
            $fieldName = $propertyMetadata->fieldName;

            if (!isset($data[$fieldName])) {
                continue;
            }

            $value = $data[$fieldName];

            if (!$cryptographer->supports($value)) {
                continue;
            }

            $info = $propertyMetadata->extras[SensitiveDataInfo::class];
            assert($info instanceof SensitiveDataInfo);

            $subjectId = $subjectIds->get($info->subjectIdName);

            try {
                $data[$fieldName] = $cryptographer->decrypt($subjectId, $value);
            } catch (DecryptionFailed | CipherKeyNotExists) {
                $fallback = $info->fallback instanceof Closure
                    ? ($info->fallback)($subjectId)
                    : $info->fallback;

                $normalizer = $propertyMetadata->normalizer;

                if ($normalizer !== null) {
                    $fallback = $normalizer->normalize($fallback, $context);
                }

                $data[$fieldName] = $fallback;
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
                    $subjectId = $property->normalizer->normalize($subjectId, $context);
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
