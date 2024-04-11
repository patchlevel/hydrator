<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography\Store;

use RuntimeException;

use function sprintf;

final class CipherKeyNotExists extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Cipher key with subject id "%s" not found.', $id));
    }
}
