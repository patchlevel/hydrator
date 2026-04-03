<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography;

use Patchlevel\Hydrator\Cryptography\PayloadCryptographer;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;

/** @experimental */
final readonly class LegacyCryptographyDecryptMiddleware implements Middleware
{
    public function __construct(
        private PayloadCryptographer $payloadCryptographer,
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
        $processedData = $this->payloadCryptographer->decrypt($metadata, $data);

        if ($processedData !== $data) {
            $context[self::class] = true;
        }

        return $stack->next()->hydrate($metadata, $processedData, $context, $stack);
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
        return $stack->next()->extract($metadata, $object, $context, $stack);
    }
}
