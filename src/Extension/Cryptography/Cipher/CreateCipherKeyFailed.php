<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Cipher;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;
use Throwable;

use function sprintf;

/** @experimental */
final class CreateCipherKeyFailed extends RuntimeException implements HydratorException
{
    private function __construct(string $message, Throwable|null $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function forMethod(string $method, string $reason): self
    {
        return new self(sprintf('Failed to create cipher key for method "%s": %s', $method, $reason));
    }

    public static function invalidKeyLength(string $method): self
    {
        return new self(sprintf('Invalid key length for method "%s".', $method));
    }
}
