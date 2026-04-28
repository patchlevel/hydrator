<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Cipher;

interface CipherKeyFactory
{
    /**
     * @param non-empty-string $subjectId
     *
     * @throws CreateCipherKeyFailed
     */
    public function __invoke(string $subjectId): CipherKey;
}
