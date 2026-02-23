<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Cipher;

interface CipherKeyFactory
{
    /** @throws CreateCipherKeyFailed */
    public function __invoke(): CipherKey;
}
