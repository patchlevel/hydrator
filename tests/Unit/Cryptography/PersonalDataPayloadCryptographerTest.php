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
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\Hydrator\Cryptography\PersonalDataPayloadCryptographer */
final class PersonalDataPayloadCryptographerTest extends TestCase
{
    use ProphecyTrait;

    public function testSkipEncrypt(): void
    {
        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get(Argument::any())->shouldNotBeCalled();

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipher = $this->prophesize(Cipher::class);

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
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

        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get('foo')->willThrow(new CipherKeyNotExists('foo'));
        $cipherKeyStore->store('foo', $cipherKey)->shouldBeCalled();

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipherKeyFactory->__invoke()->willReturn($cipherKey)->shouldBeCalledOnce();

        $cipher = $this->prophesize(Cipher::class);
        $cipher
            ->encrypt($cipherKey, 'info@patchlevel.de')
            ->willReturn('encrypted')
            ->shouldBeCalledOnce();

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
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

        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get('foo')->willReturn($cipherKey);
        $cipherKeyStore->store('foo', Argument::type(CipherKey::class))->shouldNotBeCalled();

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipherKeyFactory->__invoke()->shouldNotBeCalled();

        $cipher = $this->prophesize(Cipher::class);
        $cipher
            ->encrypt($cipherKey, 'info@patchlevel.de')
            ->willReturn('encrypted')
            ->shouldBeCalledOnce();

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $result = $cryptographer->encrypt($this->metadata(PersonalDataProfileCreated::class), ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertEquals(['id' => 'foo', 'email' => 'encrypted'], $result);
    }

    public function testSkipDecrypt(): void
    {
        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get(Argument::any())->shouldNotBeCalled();

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipher = $this->prophesize(Cipher::class);

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $payload = ['id' => 'foo', 'email' => 'info@patchlevel.de'];

        $result = $cryptographer->decrypt($this->metadata(PersonalData::class), ['id' => 'foo', 'email' => 'info@patchlevel.de']);

        self::assertSame($payload, $result);
    }

    public function testDecryptWithMissingKey(): void
    {
        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get('foo')->willThrow(new CipherKeyNotExists('foo'));

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipherKeyFactory->__invoke()->shouldNotBeCalled();

        $cipher = $this->prophesize(Cipher::class);
        $cipher->decrypt()->shouldNotBeCalled();

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
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

        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get('foo')->willReturn($cipherKey);
        $cipherKeyStore->store('foo', Argument::type(CipherKey::class))->shouldNotBeCalled();

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipherKeyFactory->__invoke()->shouldNotBeCalled();

        $cipher = $this->prophesize(Cipher::class);
        $cipher
            ->decrypt($cipherKey, 'encrypted')
            ->willThrow(new DecryptionFailed())
            ->shouldBeCalledOnce();

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $result = $cryptographer->decrypt($this->metadata(PersonalDataProfileCreated::class), ['id' => 'foo', 'email' => 'encrypted']);

        self::assertEquals(['id' => 'foo', 'email' => new Email('unknown')], $result);
    }

    public function testDecryptWithExistingKey(): void
    {
        $cipherKey = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyStore->get('foo')->willReturn($cipherKey);
        $cipherKeyStore->store('foo', Argument::type(CipherKey::class))->shouldNotBeCalled();

        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipherKeyFactory->__invoke()->shouldNotBeCalled();

        $cipher = $this->prophesize(Cipher::class);
        $cipher
            ->decrypt($cipherKey, 'encrypted')
            ->willReturn('info@patchlevel.de')
            ->shouldBeCalledOnce();

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $result = $cryptographer->decrypt($this->metadata(PersonalDataProfileCreated::class), ['id' => 'foo', 'email' => 'encrypted']);

        self::assertEquals(['id' => 'foo', 'email' => 'info@patchlevel.de'], $result);
    }

    public function testUnsupportedSubjectId(): void
    {
        $this->expectException(UnsupportedSubjectId::class);

        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipher = $this->prophesize(Cipher::class);

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $cryptographer->decrypt($this->metadata(PersonalDataProfileCreated::class), ['id' => null, 'email' => 'encrypted']);
    }

    public function testMissingSubjectId(): void
    {
        $this->expectException(MissingSubjectId::class);

        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);
        $cipherKeyFactory = $this->prophesize(CipherKeyFactory::class);
        $cipher = $this->prophesize(Cipher::class);

        $cryptographer = new PersonalDataPayloadCryptographer(
            $cipherKeyStore->reveal(),
            $cipherKeyFactory->reveal(),
            $cipher->reveal(),
        );

        $cryptographer->decrypt($this->metadata(PersonalDataProfileCreated::class), ['email' => 'encrypted']);
    }

    public function testCreateWithOpenssl(): void
    {
        $cipherKeyStore = $this->prophesize(CipherKeyStore::class);

        $cryptographer = PersonalDataPayloadCryptographer::createWithOpenssl(
            $cipherKeyStore->reveal(),
        );

        self::assertInstanceOf(PersonalDataPayloadCryptographer::class, $cryptographer);
    }

    /** @param class-string $class */
    private function metadata(string $class): ClassMetadata
    {
        return (new AttributeMetadataFactory())->metadata($class);
    }
}
