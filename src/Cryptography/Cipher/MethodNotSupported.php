<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography\Cipher;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

use function sprintf;

final class MethodNotSupported extends RuntimeException implements HydratorException
{
    public function __construct(string $method)
    {
        parent::__construct(sprintf('Method %s not supported.', $method));
    }
}
