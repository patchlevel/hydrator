<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Cipher;

use DateTimeImmutable;
use SensitiveParameter;

/** @experimental */
final class CipherKey
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $subjectId
     * @param non-empty-string $key
     * @param non-empty-string $method
     */
    public function __construct(
        public readonly string $id,
        public readonly string $subjectId,
        #[SensitiveParameter]
        public readonly string $key,
        public readonly string $method,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
