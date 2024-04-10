<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography\Cipher;

use RuntimeException;

final class CreateCipherKeyFailed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Create cipher key failed.');
    }
}
