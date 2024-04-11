<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

interface PayloadCryptographer
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function encrypt(ClassMetadata $metadata, array $data): array;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function decrypt(ClassMetadata $metadata, array $data): array;
}
