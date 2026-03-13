<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography;

use DateTimeImmutable;
use Patchlevel\Hydrator\Extension\Cryptography\BaseCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\Cipher;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\EncryptedData;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BaseCryptographer::class)]
final class BaseCryptographerTest extends TestCase
{
    public function testEncrypt(): void
    {
        $cipherKey = new CipherKey('key-123', 'subject-foo', 'secret-key', 'aes-256-gcm', new DateTimeImmutable());
        $encryptionParameter = new EncryptedData('encrypted-data', 'aes-256-gcm', 'random-nonce', 'auth-tag');

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('currentKeyFor')->with('subject-foo')->willReturn($cipherKey);

        $cipherKeyStore
            ->expects($this->never())
            ->method('store');

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->never())->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('encrypt')->with($cipherKey, 'info@patchlevel.de')
            ->willReturn($encryptionParameter);

        $cryptographer = new BaseCryptographer(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $expected = [
            'v' => 1,
            'a' => 'aes-256-gcm',
            'k' => 'key-123',
            'd' => 'encrypted-data',
            'n' => 'random-nonce',
            't' => 'auth-tag',
        ];

        self::assertEquals($expected, $cryptographer->encrypt('subject-foo', 'info@patchlevel.de'));
    }

    public function testEncryptWithoutKey(): void
    {
        $cipherKey = new CipherKey('key-456', 'subject-bar', 'secret-key', 'aes-256-gcm', new DateTimeImmutable());
        $encryptionParameter = new EncryptedData('encrypted-data', 'aes-256-gcm', 'random-nonce', 'auth-tag');

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('currentKeyFor')->with('subject-bar')->willThrowException(CipherKeyNotExists::forSubjectId('subject-bar'));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->once())->method('__invoke')->with('subject-bar')->willReturn($cipherKey);

        $cipherKeyStore
            ->expects($this->once())
            ->method('store')
            ->with($cipherKey);

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('encrypt')->with($cipherKey, 'info@patchlevel.de')
            ->willReturn($encryptionParameter);

        $cryptographer = new BaseCryptographer(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $expected = [
            'v' => 1,
            'a' => 'aes-256-gcm',
            'k' => 'key-456',
            'd' => 'encrypted-data',
            'n' => 'random-nonce',
            't' => 'auth-tag',
        ];

        self::assertEquals($expected, $cryptographer->encrypt('subject-bar', 'info@patchlevel.de'));
    }

    public function testDecrypt(): void
    {
        $cipherKey = new CipherKey('key-789', 'subject-baz', 'secret-key', 'aes-256-gcm', new DateTimeImmutable());

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('key-789')->willReturn($cipherKey);

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->never())->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('decrypt')->with(
            $cipherKey,
            $this->callback(static function (EncryptedData $param) {
                return $param->data === 'encrypted-data'
                    && $param->method === 'aes-256-gcm'
                    && $param->nonce === 'random-nonce'
                    && $param->tag === 'auth-tag';
            }),
        )->willReturn('info@patchlevel.de');

        $cryptographer = new BaseCryptographer(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        self::assertEquals(
            'info@patchlevel.de',
            $cryptographer->decrypt(
                'subject-baz',
                [
                    'v' => 1,
                    'a' => 'aes-256-gcm',
                    'k' => 'key-789',
                    'd' => 'encrypted-data',
                    'n' => 'random-nonce',
                    't' => 'auth-tag',
                ],
            ),
        );
    }

    #[DataProvider('dataProviderSupports')]
    public function testSupports(mixed $value, bool $supported): void
    {
        $cryptographer = new BaseCryptographer(
            $this->createMock(Cipher::class),
            $this->createMock(CipherKeyStore::class),
            $this->createMock(CipherKeyFactory::class),
        );

        self::assertEquals($supported, $cryptographer->supports($value));
    }

    /** @return iterable<array{0: mixed, 1: bool}> */
    public static function dataProviderSupports(): iterable
    {
        yield ['foo', false];
        yield [[], false];
        yield [null, false];
        yield [['v' => 'foo'], false];
        yield [['v' => 2], false];
        yield [['v' => 1], false]; // missing required fields
        yield [['v' => 1, 'a' => 'aes-256-gcm'], false]; // missing k and d
        yield [['v' => 1, 'a' => 'aes-256-gcm', 'k' => 'key-123', 'd' => 'data'], true];
    }
}
