<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

final class SensitiveDataInfo
{
    public function __construct(
        public readonly string $subjectIdName,
        public readonly mixed $fallback = null,
    ) {
    }
}
