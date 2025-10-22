<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use Patchlevel\Hydrator\Event\PostExtract;
use Patchlevel\Hydrator\Event\PreExtract;
use Patchlevel\Hydrator\Event\PreHydrate;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Stringable;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function is_int;
use function is_string;

final class CryptographySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PayloadCryptographer $cryptography,
    ) {
    }

    public function preHydrate(PreHydrate $event): void
    {
        $parentSubjectId = $event->context['subjectId'] ?? null;

        $currentSubjectId = $this->subjectIdFromData($event->data, $event->metadata) ?: $parentSubjectId;
        $event->data = $this->cryptography->decrypt($event->metadata, $event->data, $currentSubjectId);

        $event->context['subjectId'] = $currentSubjectId;
    }

    public function preExtract(PreExtract $event): void
    {
        $parentSubjectId = $event->context['subjectId'] ?? null;

        $currentSubjectId = $this->subjectIdFromObject($event->object, $event->metadata) ?: $parentSubjectId;

        $event->context['subjectId'] = $currentSubjectId;
    }

    public function postExtract(PostExtract $event): void
    {
        $event->data = $this->cryptography->encrypt(
            $event->metadata,
            $event->data,
            $event->context['subjectId'] ?? null
        );
    }

    /** @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>> */
    public static function getSubscribedEvents(): array
    {
        return [
            PreHydrate::class => 'preHydrate',
            PreExtract::class => 'preExtract',
            PostExtract::class => 'postExtract',
        ];
    }

    private function subjectIdFromObject(object $object, ClassMetadata $metadata): string|null
    {
        $subjectIdField = $metadata->dataSubjectIdField();

        if ($subjectIdField === null) {
            return null;
        }

        $property = $metadata->propertyForField($subjectIdField);

        $value = $property->getValue($object);

        if ($value === null) {
            return null;
        }

        $normalizer = $property->normalizer();

        if ($normalizer !== null) {
            $value = $normalizer->normalize($value);
        }

        if ($value instanceof Stringable) {
            $value = (string)$value;
        }

        if (is_int($value)) {
            $value = (string)$value;
        }

        if (!is_string($value)) {
            throw new UnsupportedSubjectId($metadata->className(), $subjectIdField, $value);
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function subjectIdFromData(array $data, ClassMetadata $metadata): string|null
    {
        $subjectIdField = $metadata->dataSubjectIdField();

        if ($subjectIdField === null) {
            return null;
        }

        $value = $data[$subjectIdField] ?? null;

        if ($value === null) {
            return null;
        }

        if ($value instanceof Stringable) {
            $value = (string)$value;
        }

        if (is_int($value)) {
            $value = (string)$value;
        }

        if (!is_string($value)) {
            throw new UnsupportedSubjectId($metadata->className(), $subjectIdField, $value);
        }

        return $value;
    }
}
