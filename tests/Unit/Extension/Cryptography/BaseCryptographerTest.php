<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography;

use Patchlevel\Hydrator\Extension\Cryptography\BaseCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\Cipher;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKeyFactory;
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
        $cipherKey = new CipherKey('foo', 'methodA', 'random');

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willReturn($cipherKey);

        $cipherKeyStore
            ->expects($this->never())
            ->method('store')
            ->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->never())->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('encrypt')->with($cipherKey, 'info@patchlevel.de')
            ->willReturn('encrypted');

        $cryptographer = new BaseCryptographer(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $expected = [
            '__enc' => 'v1',
            'data' => 'encrypted',
            'method' => 'methodA',
            'iv' => 'random',
        ];

        self::assertEquals($expected, $cryptographer->encrypt('foo', 'info@patchlevel.de'));
    }

    public function testEncryptWithoutKey(): void
    {
        $cipherKey = new CipherKey('foo', 'methodA', 'random');

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willThrowException(new CipherKeyNotExists('foo'));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->once())->method('__invoke')->willReturn($cipherKey);

        $cipherKeyStore
            ->expects($this->once())
            ->method('store')
            ->with('foo', $cipherKey);

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('encrypt')->with($cipherKey, 'info@patchlevel.de')
            ->willReturn('encrypted');

        $cryptographer = new BaseCryptographer(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $expected = [
            '__enc' => 'v1',
            'data' => 'encrypted',
            'method' => 'methodA',
            'iv' => 'random',
        ];

        self::assertEquals($expected, $cryptographer->encrypt('foo', 'info@patchlevel.de'));
    }

    public function testDecrypt(): void
    {
        $cipherKey = new CipherKey('foo', 'methodA', 'random');

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willReturn($cipherKey);

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->never())->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('decrypt')->with($cipherKey, 'encrypted')
            ->willReturn('info@patchlevel.de');

        $cryptographer = new BaseCryptographer(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        self::assertEquals(
            'info@patchlevel.de',
            $cryptographer->decrypt(
                'foo',
                [
                    '__enc' => 'v1',
                    'data' => 'encrypted',
                    'method' => 'methodA',
                    'iv' => 'random',
                ],
            ),
        );
    }

    public function testDecryptWithFallback(): void
    {
        $cipherKey = new CipherKey('foo', 'methodA', 'random');

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willReturn($cipherKey);

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->never())->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('decrypt')->with($cipherKey, 'encrypted')
            ->willReturn('info@patchlevel.de');

        $cryptographer = new BaseCryptographer(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        self::assertEquals(
            'info@patchlevel.de',
            $cryptographer->decrypt(
                'foo',
                [
                    '__enc' => 'v1',
                    'data' => 'encrypted',
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
        yield [['__enc' => 'foo'], false];
        yield [['__enc' => 'v1'], true];
    }
}
