<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography;

use Patchlevel\Hydrator\Cryptography\Cipher\Cipher;
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\Hydrator\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Cryptography\CryptographyMetadataFactory;
use Patchlevel\Hydrator\Cryptography\CryptographyMiddleware;
use Patchlevel\Hydrator\Cryptography\MissingSubjectId;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Cryptography\SubjectIds;
use Patchlevel\Hydrator\Cryptography\UnsupportedSubjectId;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Middleware\TransformMiddleware;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\SensitiveDataProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\SensitiveDataProfileCreatedFallbackCallback;
use Patchlevel\Hydrator\Tests\Unit\Fixture\SensitiveDataWithStringableSubjectId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\StringableSubjectId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CryptographyMiddleware::class)]
final class CryptographyMiddlewareTest extends TestCase
{
    public function testSkipEncrypt(): void
    {
        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->expects($this->never())->method('get');

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipher = $this->createMock(Cipher::class);

        $middleware = new CryptographyMiddleware(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $object = new ProfileCreated(
            ProfileId::fromString('foo'),
            Email::fromString('info@patchlevel.de'),
        );

        $expected = ['profileId' => 'foo', 'email' => 'info@patchlevel.de'];

        $metadata = $this->metadata(ProfileCreated::class);

        $otherMiddleware = $this->createMock(Middleware::class);
        $stack = new Stack([$otherMiddleware]);

        $otherMiddleware
            ->expects($this->once())
            ->method('extract')
            ->with($metadata, $object, [SubjectIds::class => new SubjectIds()], $stack)
            ->willReturn($expected);

        $result = $middleware->extract(
            $metadata,
            $object,
            [],
            $stack,
        );

        self::assertSame($expected, $result);
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

        $object = new SensitiveDataProfileCreated(
            ProfileId::fromString('foo'),
            Email::fromString('info@patchlevel.de'),
        );

        $metadata = $this->metadata(SensitiveDataProfileCreated::class);

        $otherMiddleware = $this->createMock(Middleware::class);
        $stack = new Stack([$otherMiddleware]);

        $otherMiddleware
            ->expects($this->once())
            ->method('extract')
            ->with($metadata, $object, [SubjectIds::class => new SubjectIds(['default' => 'foo'])], $stack)
            ->willReturn(['id' => 'foo', 'email' => 'info@patchlevel.de']);

        $middleware = new CryptographyMiddleware(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $result = $middleware->extract(
            $metadata,
            $object,
            [],
            $stack,
        );

        self::assertEquals(['id' => 'foo', '!email' => 'encrypted'], $result);
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

        $object = new SensitiveDataProfileCreated(
            ProfileId::fromString('foo'),
            Email::fromString('info@patchlevel.de'),
        );

        $metadata = $this->metadata(SensitiveDataProfileCreated::class);

        $otherMiddleware = $this->createMock(Middleware::class);
        $stack = new Stack([$otherMiddleware]);

        $otherMiddleware
            ->expects($this->once())
            ->method('extract')
            ->with($metadata, $object, [SubjectIds::class => new SubjectIds(['default' => 'foo'])], $stack)
            ->willReturn(['id' => 'foo', 'email' => 'info@patchlevel.de']);

        $middleware = new CryptographyMiddleware(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $result = $middleware->extract(
            $metadata,
            $object,
            [],
            $stack,
        );

        self::assertEquals(['id' => 'foo', '!email' => 'encrypted'], $result);
    }

    public function testEncryptWithoutEncryptedFieldPrefix(): void
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

        $object = new SensitiveDataProfileCreated(
            ProfileId::fromString('foo'),
            Email::fromString('info@patchlevel.de'),
        );

        $metadata = $this->metadata(SensitiveDataProfileCreated::class);

        $otherMiddleware = $this->createMock(Middleware::class);
        $stack = new Stack([$otherMiddleware]);

        $otherMiddleware
            ->expects($this->once())
            ->method('extract')
            ->with($metadata, $object, [SubjectIds::class => new SubjectIds(['default' => 'foo'])], $stack)
            ->willReturn(['id' => 'foo', 'email' => 'info@patchlevel.de']);

        $middleware = new CryptographyMiddleware(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
            null,
        );

        $result = $middleware->extract(
            $metadata,
            $object,
            [],
            $stack,
        );

        self::assertEquals(['id' => 'foo', 'email' => 'encrypted'], $result);
    }

    public function testSkipDecrypt(): void
    {
        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->expects($this->never())->method('get');

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipher = $this->createMock(Cipher::class);

        $data = ['profileId' => 'foo', 'email' => 'info@patchlevel.de'];

        $expected = new ProfileCreated(
            ProfileId::fromString('foo'),
            Email::fromString('info@patchlevel.de'),
        );

        $metadata = $this->metadata(ProfileCreated::class);

        $otherMiddleware = $this->createMock(Middleware::class);
        $stack = new Stack([$otherMiddleware]);

        $otherMiddleware
            ->expects($this->once())
            ->method('hydrate')
            ->with($metadata, $data, [SubjectIds::class => new SubjectIds()], $stack)
            ->willReturn($expected);

        $middleware = new CryptographyMiddleware(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $result = $middleware->hydrate(
            $metadata,
            $data,
            [],
            $stack,
        );

        self::assertSame($expected, $result);
    }

    public function testDecryptWithMissingKey(): void
    {
        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willThrowException(new CipherKeyNotExists('foo'));

        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipherKeyFactory->expects($this->never())->method('__invoke');

        $cipher = $this->createMock(Cipher::class);
        $cipher->expects($this->never())->method('decrypt');

        $middleware = new CryptographyMiddleware(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $result = $middleware->hydrate(
            $this->metadata(SensitiveDataProfileCreated::class),
            ['id' => 'foo', 'email' => 'encrypted'],
            [],
            new Stack([new TransformMiddleware()]),
        );

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

        $middleware = new CryptographyMiddleware(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $result = $middleware->hydrate(
            $this->metadata(SensitiveDataProfileCreated::class),
            ['id' => 'foo', 'email' => 'encrypted'],
            [],
            new Stack([new TransformMiddleware()]),
        );

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

        $middleware = new CryptographyMiddleware(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $result = $middleware->hydrate(
            $this->metadata(SensitiveDataProfileCreatedFallbackCallback::class),
            ['id' => 'foo', 'email' => 'encrypted'],
            [],
            new Stack([new TransformMiddleware()]),
        );

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

        $middleware = new CryptographyMiddleware(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
            null,
        );

        $result = $middleware->hydrate(
            $this->metadata(SensitiveDataProfileCreated::class),
            ['id' => 'foo', 'email' => 'encrypted'],
            [],
            new Stack([new TransformMiddleware()]),
        );

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

        $middleware = new CryptographyMiddleware(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $result = $middleware->hydrate(
            $this->metadata(SensitiveDataProfileCreated::class),
            ['id' => 'foo', '!email' => 'encrypted'],
            [],
            new Stack([new TransformMiddleware()]),
        );

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

        $middleware = new CryptographyMiddleware(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $result = $middleware->hydrate(
            $this->metadata(SensitiveDataProfileCreated::class),
            ['id' => 'foo', 'email' => 'info@patchlevel.de'],
            [],
            new Stack([new TransformMiddleware()]),
        );

        self::assertEquals(['id' => 'foo', 'email' => 'info@patchlevel.de'], $result);
    }

    public function testUnsupportedSubjectId(): void
    {
        $this->expectException(UnsupportedSubjectId::class);

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipher = $this->createMock(Cipher::class);

        $middleware = new CryptographyMiddleware(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $middleware->hydrate(
            $this->metadata(SensitiveDataProfileCreated::class),
            ['id' => null, 'email' => 'encrypted'],
            [],
            new Stack([new TransformMiddleware()]),
        );
    }

    public function testMissingSubjectId(): void
    {
        $this->expectException(MissingSubjectId::class);

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyFactory = $this->createMock(CipherKeyFactory::class);
        $cipher = $this->createMock(Cipher::class);

        $middleware = new CryptographyMiddleware(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $middleware->hydrate(
            $this->metadata(SensitiveDataProfileCreated::class),
            ['email' => 'encrypted'],
            [],
            new Stack([new TransformMiddleware()]),
        );
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

        $middleware = new CryptographyMiddleware(
            $cipher,
            $cipherKeyStore,
            $cipherKeyFactory,
        );

        $subjectId = new StringableSubjectId('user-123');

        $result = $middleware->extract(
            $this->metadata(SensitiveDataWithStringableSubjectId::class),
            ['subjectId' => $subjectId, 'name' => 'John Doe'],
            [],
            new Stack([new TransformMiddleware()]),
        );

        self::assertEquals(['subjectId' => $subjectId, 'name' => 'encrypted'], $result);
    }

    public function testCreateWithOpenssl(): void
    {
        $cipherKey = new CipherKey('foo', 'aes128', 'baz');

        $cipherKeyStore = $this->createMock(CipherKeyStore::class);
        $cipherKeyStore->method('get')->with('foo')->willReturn($cipherKey);
        $cipherKeyStore
            ->expects($this->never())
            ->method('store')
            ->with('foo', $this->isInstanceOf(CipherKey::class));

        $middleware = CryptographyMiddleware::createWithOpenssl($cipherKeyStore);

        $data = ['id' => 'foo', 'email' => 'info@patchlevel.de'];

        $enrcyptedData = $middleware->extract(
            $this->metadata(SensitiveDataProfileCreated::class),
            $data,
            [],
            new Stack([new TransformMiddleware()]),
        );

        self::assertNotSame('info@patchlevel.de', $enrcyptedData['email']);
        self::assertSame('aUYxMzQ2bm80cUNCcE1wOUsveitUSmdGaHpYYjNoQWp1VGxTQXVITXRDVT0=', $enrcyptedData['email']);

        $decryptedData = $middleware->hydrate(
            $this->metadata(SensitiveDataProfileCreated::class),
            $enrcyptedData,
            [],
            new Stack([new TransformMiddleware()])
        );

        self::assertSame($data, $decryptedData);
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
