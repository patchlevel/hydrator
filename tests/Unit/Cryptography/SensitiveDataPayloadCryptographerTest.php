<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography;

use Patchlevel\Hydrator\Attribute\SensitiveData;
use Patchlevel\Hydrator\Cryptography\Cipher\Cipher;
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\Hydrator\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Cryptography\CryptographyMetadataFactory;
use Patchlevel\Hydrator\Cryptography\MissingSubjectId;
use Patchlevel\Hydrator\Cryptography\SensitiveDataPayloadCryptographer;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Cryptography\UnsupportedSubjectId;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\SensitiveDataProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\SensitiveDataProfileCreatedFallbackCallback;
use Patchlevel\Hydrator\Tests\Unit\Fixture\SensitiveDataWithStringableSubjectId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\StringableSubjectId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\Hydrator\Cryptography\SensitiveDataPayloadCryptographer */
final class SensitiveDataPayloadCryptographerTest extends TestCase
{
    public function testSkipEncrypt(): void
    {
        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->expects($this->never())->method('get');

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipher = $this->createMock(Cipher::class);

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $payload = ['id' => 'foo', 'email' => 'info@patchlevel.de'];

        $result = $cryptographer->encrypt($this->metadata(SensitiveData::class), ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertSame($payload, $result);
    }

    public function testEncryptWithMissingKey(): void
    {
        $cipherKey = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willThrowException(new CipherKeyNotExists('foo'));
        $cipherKeyStore->expects($this->once())->method('store')->with('foo', $cipherKey);

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->once())->method('__invoke')->willReturn($cipherKey);

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('encrypt')->with($cipherKey, 'info@patchlevel.de')
            ->willReturn('encrypted');

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $result = $cryptographer->encrypt($this->metadata(SensitiveDataProfileCreated::class), ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertEquals(['id' => 'foo', 'email' => 'encrypted'], $result);
    }

    public function testEncryptWithExistingKey(): void
    {
        $cipherKey = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

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

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $result = $cryptographer->encrypt($this->metadata(SensitiveDataProfileCreated::class), ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertEquals(['id' => 'foo', 'email' => 'encrypted'], $result);
    }

    public function testEncryptWithExistingKeyEncryptedFieldName(): void
    {
        $cipherKey = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

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

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
            true,
        );

        $result = $cryptographer->encrypt($this->metadata(SensitiveDataProfileCreated::class), ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertEquals(['id' => 'foo', '!email' => 'encrypted'], $result);
    }

    public function testSkipDecrypt(): void
    {
        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->expects($this->never())->method('get');

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipher = $this->createMock(Cipher::class);

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $payload = ['id' => 'foo', 'email' => 'info@patchlevel.de'];

        $result = $cryptographer->decrypt($this->metadata(SensitiveData::class), ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertSame($payload, $result);
    }

    public function testDecryptWithMissingKey(): void
    {
        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willThrowException(new CipherKeyNotExists('foo'));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->never())->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->never())->method('decrypt');

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $result = $cryptographer->decrypt($this->metadata(SensitiveDataProfileCreated::class), ['id' => 'foo', 'email' => 'encrypted']);

        self::assertEquals(['id' => 'foo', 'email' => new Email('unknown')], $result);
    }

    public function testDecryptWithInvalidKey(): void
    {
        $cipherKey = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willReturn($cipherKey);
        $cipherKeyStore
            ->expects($this->never())
            ->method('store')
            ->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->never())->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('decrypt')->with($cipherKey, 'encrypted')
            ->willThrowException(new DecryptionFailed());

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $result = $cryptographer->decrypt($this->metadata(SensitiveDataProfileCreated::class), ['id' => 'foo', 'email' => 'encrypted']);

        self::assertEquals(['id' => 'foo', 'email' => new Email('unknown')], $result);
    }

    public function testDecryptWithInvalidKeyWithFallbackCallback(): void
    {
        $cipherKey = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willReturn($cipherKey);
        $cipherKeyStore->expects($this->never())->method('store')->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->never())->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('decrypt')->with($cipherKey, 'encrypted')
            ->willThrowException(new DecryptionFailed());

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $result = $cryptographer->decrypt($this->metadata(SensitiveDataProfileCreatedFallbackCallback::class), ['id' => 'foo', 'email' => 'encrypted']);

        self::assertEquals(['id' => 'foo', 'email' => new Email('foo@example.com')], $result);
    }

    public function testDecryptWithValidKey(): void
    {
        $cipherKey = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willReturn($cipherKey);
        $cipherKeyStore->expects($this->never())->method('store')->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->never())->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('decrypt')->with($cipherKey, 'encrypted')
            ->willReturn('info@patchlevel.de');

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
            false,
        );

        $result = $cryptographer->decrypt($this->metadata(SensitiveDataProfileCreated::class), ['id' => 'foo', 'email' => 'encrypted']);

        self::assertEquals(['id' => 'foo', 'email' => 'info@patchlevel.de'], $result);
    }

    public function testDecryptWithValidKeyAndEncryptedFieldName(): void
    {
        $cipherKey = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willReturn($cipherKey);
        $cipherKeyStore->expects($this->never())->method('store')->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->never())->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher
            ->expects($this->once())
            ->method('decrypt')
            ->with($cipherKey, 'encrypted')
            ->willReturn('info@patchlevel.de');

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
            true,
        );

        $result = $cryptographer->decrypt($this->metadata(SensitiveDataProfileCreated::class), ['id' => 'foo', '!email' => 'encrypted']);

        self::assertEquals(['id' => 'foo', 'email' => 'info@patchlevel.de'], $result);
    }

    public function testDecryptWithValidKeyAndEncryptedFieldNameWithoutEncryptedData(): void
    {
        $cipherKey = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willReturn($cipherKey);
        $cipherKeyStore->expects($this->never())->method('store')->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->never())->method('__invoke');

        $cipher = $this->createMock(Cipher::class);

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
            true,
        );

        $result = $cryptographer->decrypt($this->metadata(SensitiveDataProfileCreated::class), ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertEquals(['id' => 'foo', 'email' => 'info@patchlevel.de'], $result);
    }

    public function testDecryptWithValidKeyAndEncryptedFieldNameAndFallbackFieldName(): void
    {
        $cipherKey = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willReturn($cipherKey);
        $cipherKeyStore->expects($this->never())->method('store')->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->never())->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('decrypt')->with($cipherKey, 'encrypted')
            ->willReturn('info@patchlevel.de');

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
            true,
            true,
        );

        $result = $cryptographer->decrypt($this->metadata(SensitiveDataProfileCreated::class), ['id' => 'foo', 'email' => 'encrypted']);

        self::assertEquals(['id' => 'foo', 'email' => 'info@patchlevel.de'], $result);
    }

    public function testUnsupportedSubjectId(): void
    {
        $this->expectException(UnsupportedSubjectId::class);

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipher = $this->createMock(Cipher::class);

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $cryptographer->decrypt($this->metadata(SensitiveDataProfileCreated::class), ['id' => null, 'email' => 'encrypted']);
    }

    public function testMissingSubjectId(): void
    {
        $this->expectException(MissingSubjectId::class);

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipher = $this->createMock(Cipher::class);

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $cryptographer->decrypt($this->metadata(SensitiveDataProfileCreated::class), ['email' => 'encrypted']);
    }

    public function testStringableSubjectId(): void
    {
        $cipherKey = new CipherKey(
            'user-123',
            'bar',
            'baz',
        );

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->willThrowException(new CipherKeyNotExists('user-123'));
        $cipherKeyStore->expects($this->once())->method('store')->with('user-123', $cipherKey);

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->once())->method('__invoke')->willReturn($cipherKey);

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('encrypt')->with($cipherKey, 'John Doe')
            ->willReturn('encrypted');

        $cryptographer = new SensitiveDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $subjectId = new StringableSubjectId('user-123');

        $result = $cryptographer->encrypt(
            $this->metadata(SensitiveDataWithStringableSubjectId::class),
            ['subjectId' => $subjectId, 'name' => 'John Doe'],
        );

        self::assertEquals(['subjectId' => $subjectId, 'name' => 'encrypted'], $result);
    }

    public function testCreateWithOpenssl(): void
    {
        $cipherKeyStore = $this->createMock(CipherKeyStore::class);

        $cryptographer = SensitiveDataPayloadCryptographer::createWithOpenssl(
            $cipherKeyStore,
        );

        self::assertInstanceOf(SensitiveDataPayloadCryptographer::class, $cryptographer);
    }

    /** @param class-string $class */
    private function metadata(string $class): ClassMetadata
    {
        $factory = new CryptographyMetadataFactory(
            new AttributeMetadataFactory(),
        );

        return $factory->metadata($class);
    }
}
