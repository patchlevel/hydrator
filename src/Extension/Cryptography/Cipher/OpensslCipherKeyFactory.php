<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Cipher;

use DateTimeImmutable;

use function bin2hex;
use function function_exists;
use function in_array;
use function openssl_cipher_key_length;
use function openssl_get_cipher_methods;
use function openssl_random_pseudo_bytes;

final class OpensslCipherKeyFactory implements CipherKeyFactory
{
    public const DEFAULT_METHOD = 'aes-128-gcm';

    private readonly int $keyLength;

    /** @param non-empty-string $method */
    public function __construct(
        private readonly string $method = self::DEFAULT_METHOD,
    ) {
        if (!self::methodSupported($this->method)) {
            throw new MethodNotSupported($this->method);
        }

        $keyLength = 16;

        if (function_exists('openssl_cipher_key_length')) {
            $keyLength = @openssl_cipher_key_length($this->method);
        }

        if ($keyLength === false) {
            throw new MethodNotSupported($this->method);
        }

        $this->keyLength = $keyLength;
    }

    public function __invoke(string $subjectId): CipherKey
    {
        return new CipherKey(
            bin2hex(openssl_random_pseudo_bytes(16)),
            $subjectId,
            bin2hex(openssl_random_pseudo_bytes($this->keyLength)),
            $this->method,
            new DateTimeImmutable(),
        );
    }

    /** @return list<string> */
    public static function supportedMethods(): array
    {
        return openssl_get_cipher_methods(true);
    }

    public static function methodSupported(string $method): bool
    {
        return in_array($method, self::supportedMethods(), true);
    }
}
