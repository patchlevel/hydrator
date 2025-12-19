<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

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
        $context[SubjectIds::class] = $this->resolveSubjectIds($metadata, $data, $context);

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
        $context[SubjectIds::class] = $this->resolveSubjectIds($metadata, $object, $context);

        return $stack->next()->extract($metadata, $object, $context, $stack);
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

        return $subjectIds->merge(new SubjectIds($result));
    }
}
