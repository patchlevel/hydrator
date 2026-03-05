<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Cipher;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;
use Throwable;

use function sprintf;

/** @experimental */
final class EncryptionFailed extends RuntimeException implements HydratorException
{
    private function __construct(string $message, Throwable|null $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function forMethod(string $method, Throwable|null $previous = null): self
    {
        return new self(sprintf('Encryption failed for method "%s".', $method), $previous);
    }

    public static function invalidIvLength(string $method): self
    {
        return new self(sprintf('Invalid IV length for method "%s".', $method));
    }
}
