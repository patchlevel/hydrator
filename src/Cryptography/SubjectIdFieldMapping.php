<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

final class SubjectIdFieldMapping
{
    /** @param array<string, string> $nameToField */
    public function __construct(
        public readonly array $nameToField,
    ) {
    }
}
