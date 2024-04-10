<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography\Cipher;

interface CipherKeyFactory
{
    /** @throws CreateCipherKeyFailed */
    public function __invoke(): CipherKey;
}
