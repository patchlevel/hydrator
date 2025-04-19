<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography;

use Patchlevel\Hydrator\Attribute\PersonalData;
use Patchlevel\Hydrator\Cryptography\Cipher\Cipher;
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\Hydrator\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Cryptography\MissingSubjectId;
use Patchlevel\Hydrator\Cryptography\PersonalDataPayloadCryptographer;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Cryptography\UnsupportedSubjectId;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\PersonalDataProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\PersonalDataProfileCreatedFallbackCallback;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\Hydrator\Cryptography\PersonalDataPayloadCryptographer */
final class PersonalDataPayloadCryptographerTest extends TestCase
{
    public function testSkipEncrypt(): void
    {
        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get');

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipher = $this->createMock(Cipher::class);

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $payload = ['id' => 'foo', 'email' => 'info@patchlevel.de'];

        $result = $cryptographer->encrypt($this->metadata(PersonalData::class), ['id' => 'foo', 'email' => 'info@patchlevel.de']);

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

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $result = $cryptographer->encrypt($this->metadata(PersonalDataProfileCreated::class), ['id' => 'foo', 'email' => 'info@patchlevel.de']);

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
        $cipherKeyStore->method('store')->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('encrypt')->with($cipherKey, 'info@patchlevel.de')
            ->willReturn('encrypted');

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $result = $cryptographer->encrypt($this->metadata(PersonalDataProfileCreated::class), ['id' => 'foo', 'email' => 'info@patchlevel.de']);

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
        $cipherKeyStore->method('store')->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('encrypt')->with($cipherKey, 'info@patchlevel.de')
            ->willReturn('encrypted');

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
            true,
        );

        $result = $cryptographer->encrypt($this->metadata(PersonalDataProfileCreated::class), ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertEquals(['id' => 'foo', '!email' => 'encrypted'], $result);
    }

    public function testSkipDecrypt(): void
    {
        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get');

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipher = $this->createMock(Cipher::class);

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $payload = ['id' => 'foo', 'email' => 'info@patchlevel.de'];

        $result = $cryptographer->decrypt($this->metadata(PersonalData::class), ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertSame($payload, $result);
    }

    public function testDecryptWithMissingKey(): void
    {
        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willThrowException(new CipherKeyNotExists('foo'));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->method('decrypt');

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $result = $cryptographer->decrypt($this->metadata(PersonalDataProfileCreated::class), ['id' => 'foo', 'email' => 'encrypted']);

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
        $cipherKeyStore->method('store')->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('decrypt')->with($cipherKey, 'encrypted')
            ->willThrowException(new DecryptionFailed());

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $result = $cryptographer->decrypt($this->metadata(PersonalDataProfileCreated::class), ['id' => 'foo', 'email' => 'encrypted']);

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
        $cipherKeyStore->method('store')->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('decrypt')->with($cipherKey, 'encrypted')
            ->willThrowException(new DecryptionFailed());

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $result = $cryptographer->decrypt($this->metadata(PersonalDataProfileCreatedFallbackCallback::class), ['id' => 'foo', 'email' => 'encrypted']);

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
        $cipherKeyStore->method('store')->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('decrypt')->with($cipherKey, 'encrypted')
            ->willReturn('info@patchlevel.de');

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
            false,
        );

        $result = $cryptographer->decrypt($this->metadata(PersonalDataProfileCreated::class), ['id' => 'foo', 'email' => 'encrypted']);

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
        $cipherKeyStore->method('store')->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('decrypt')->with($cipherKey, 'encrypted')
            ->willReturn('info@patchlevel.de');

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
            true,
        );

        $result = $cryptographer->decrypt($this->metadata(PersonalDataProfileCreated::class), ['id' => 'foo', '!email' => 'encrypted']);

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
        $cipherKeyStore->method('store')->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->method('__invoke');

        $cipher = $this->createMock(Cipher::class);

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
            true,
        );

        $result = $cryptographer->decrypt($this->metadata(PersonalDataProfileCreated::class), ['id' => 'foo', 'email' => 'info@patchlevel.de']);

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
        $cipherKeyStore->method('store')->with('foo', $this->isInstanceOf(CipherKey::class));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->once())->method('decrypt')->with($cipherKey, 'encrypted')
            ->willReturn('info@patchlevel.de');

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
            true,
            true,
        );

        $result = $cryptographer->decrypt($this->metadata(PersonalDataProfileCreated::class), ['id' => 'foo', 'email' => 'encrypted']);

        self::assertEquals(['id' => 'foo', 'email' => 'info@patchlevel.de'], $result);
    }

    public function testUnsupportedSubjectId(): void
    {
        $this->expectException(UnsupportedSubjectId::class);

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipher = $this->createMock(Cipher::class);

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $cryptographer->decrypt($this->metadata(PersonalDataProfileCreated::class), ['id' => null, 'email' => 'encrypted']);
    }

    public function testMissingSubjectId(): void
    {
        $this->expectException(MissingSubjectId::class);

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipher = $this->createMock(Cipher::class);

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore,
            $cipherKeyFactory,
            $cipher,
        );

        $cryptographer->decrypt($this->metadata(PersonalDataProfileCreated::class), ['email' => 'encrypted']);
    }

    public function testCreateWithOpenssl(): void
    {
        $cipherKeyStore = $this->createMock(CipherKeyStore::class);

        $cryptographer = PersonalDataPayloadCryptographer::createWithOpenssl(
            $cipherKeyStore,
        );

        self::assertInstanceOf(PersonalDataPayloadCryptographer::class, $cryptographer);
    }

    /** @param class-string $class */
    private function metadata(string $class): ClassMetadata
    {
        return (new AttributeMetadataFactory())->metadata($class);
    }
}
