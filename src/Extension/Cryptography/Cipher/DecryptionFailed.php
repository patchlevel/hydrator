<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Cipher;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;
use Throwable;

use function sprintf;

/** @experimental */
final class DecryptionFailed extends RuntimeException implements HydratorException
{
    private function __construct(string $message, Throwable|null $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function forMethod(string $method, Throwable|null $previous = null): self
    {
        return new self(sprintf('Decryption failed for method "%s".', $method), $previous);
    }

    public static function invalidBase64(string $field): self
    {
        return new self(sprintf('Invalid base64 encoding in field "%s".', $field));
    }

    public static function missingKeyId(): self
    {
        return new self('Missing key ID in encrypted data.');
    }

    public static function invalidJson(Throwable|null $previous = null): self
    {
        return new self('Failed to decode JSON data.', $previous);
    }
}
