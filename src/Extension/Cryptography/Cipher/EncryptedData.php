<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Cipher;

/** @experimental */
final readonly class EncryptedData
{
    /**
     * @param non-empty-string      $data
     * @param non-empty-string      $method
     * @param non-empty-string|null $nonce
     * @param non-empty-string|null $tag
     */
    public function __construct(
        public string $data,
        public string $method,
        public string|null $nonce,
        public string|null $tag = null,
    ) {
    }
}
