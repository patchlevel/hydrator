<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Cipher;

use DateTimeImmutable;
use Generator;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\EncryptedData;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\EncryptionFailed;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\OpensslCipher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpensslCipher::class)]
final class OpensslCipherTest extends TestCase
{
    #[DataProvider('dataProvider')]
    public function testEncryptDecrypt(mixed $value): void
    {
        $cipher = new OpensslCipher();
        $key = $this->createKey();

        $encrypted = $cipher->encrypt($key, $value);

        self::assertEquals('aes-128-cbc', $encrypted->method);
        self::assertNotNull($encrypted->nonce);
        self::assertNotEmpty($encrypted->data);

        $decrypted = $cipher->decrypt($key, $encrypted);

        self::assertEquals($value, $decrypted);
    }

    public function testEncryptFailed(): void
    {
        $this->expectException(EncryptionFailed::class);

        $cipher = new OpensslCipher();
        $cipher->encrypt(new CipherKey(
            'key',
            'bar',
            'abcdefg123456789',
            'invalid-method',
            new DateTimeImmutable(),
        ), '');
    }

    public function testDecryptFailed(): void
    {
        $this->expectException(DecryptionFailed::class);

        $cipher = new OpensslCipher();

        $encryptedData = new EncryptedData('invalid-data', 'aes-128-cbc', 'invalid-nonce', null);
        $cipher->decrypt($this->createKey(), $encryptedData);
    }

    /** @return Generator<string, array{0: mixed}> */
    public static function dataProvider(): Generator
    {
        yield 'empty' => [''];
        yield 'string' => ['foo bar baz'];
        yield 'integer' => [42];
        yield 'float' => [0.5];
        yield 'null' => [null];
        yield 'true' => [true];
        yield 'false' => [false];
        yield 'array' => [['foo' => 'bar']];
        yield 'long text' => ['Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.'];
    }

    /** @param non-empty-string $key */
    private function createKey(string $key = 'key'): CipherKey
    {
        return new CipherKey(
            $key,
            'aes128',
            'abcdefg123456789',
            'aes-128-cbc',
            new DateTimeImmutable(),
        );
    }
}
