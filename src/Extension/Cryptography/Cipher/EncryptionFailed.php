<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Cipher;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

/** @experimental */
final class EncryptionFailed extends RuntimeException implements HydratorException
{
    public function __construct()
    {
        parent::__construct('Encryption failed.');
    }
}
