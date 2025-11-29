<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;

final class CryptographyMiddleware implements Middleware
{
    public function __construct(
        private readonly PayloadCryptographer $cryptography,
    ) {
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $data
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(ClassMetadata $metadata, array $data, Stack $stack): object
    {
        return $stack->next()->hydrate(
            $metadata,
            $this->cryptography->decrypt($metadata, $data),
            $stack,
        );
    }

    /**
     * @param ClassMetadata<T> $metadata
     * @param T                $object
     *
     * @return array<string, mixed>
     *
     * @template T of object
     */
    public function extract(ClassMetadata $metadata, object $object, Stack $stack): array
    {
        return $this->cryptography->encrypt(
            $metadata,
            $stack->next()->extract($metadata, $object, $stack),
        );
    }
}
