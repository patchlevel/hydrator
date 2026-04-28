<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography;

use Patchlevel\Hydrator\Extension\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Extension\Cryptography\Cryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyMetadataEnricher;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyMiddleware;
use Patchlevel\Hydrator\Extension\Cryptography\MissingSubjectIdForField;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Extension\Cryptography\SubjectIds;
use Patchlevel\Hydrator\Extension\Cryptography\UnsupportedSubjectId;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Middleware\TransformMiddleware;
use Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Fixture\SensitiveDataProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Fixture\SensitiveDataProfileCreatedFallbackCallback;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CryptographyMiddleware::class)]
final class CryptographyMiddlewareTest extends TestCase
{
    public function testUnsupportedSubjectId(): void
    {
        $this->expectException(UnsupportedSubjectId::class);

        $middleware = new CryptographyMiddleware(
            $this->createMock(Cryptographer::class),
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
        $this->expectException(MissingSubjectIdForField::class);

        $middleware = new CryptographyMiddleware(
            $this->createMock(Cryptographer::class),
        );

        $middleware->hydrate(
            $this->metadata(SensitiveDataProfileCreated::class),
            ['email' => 'encrypted'],
            [],
            new Stack([new TransformMiddleware()]),
        );
    }

    public function testSkipEncrypt(): void
    {
        $middleware = new CryptographyMiddleware(
            $this->createMock(Cryptographer::class),
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

    public function testEncrypt(): void
    {
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

        $cryptographer = $this->createMock(Cryptographer::class);
        $cryptographer->method('encrypt')->willReturn('encrypted');

        $middleware = new CryptographyMiddleware($cryptographer);

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
        $cryptographer = $this->createMock(Cryptographer::class);
        $cryptographer->expects($this->never())->method('decrypt');

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

        $middleware = new CryptographyMiddleware($cryptographer);

        $result = $middleware->hydrate(
            $metadata,
            $data,
            [],
            $stack,
        );

        self::assertSame($expected, $result);
    }

    public function testDecryptWithCipherKeyNotExists(): void
    {
        $cryptographer = $this->createMock(Cryptographer::class);
        $cryptographer->method('supports')->willReturn(true);
        $cryptographer->method('decrypt')->willThrowException(CipherKeyNotExists::forSubjectId('foo'));

        $middleware = new CryptographyMiddleware($cryptographer);

        $result = $middleware->hydrate(
            $this->metadata(SensitiveDataProfileCreated::class),
            ['id' => 'foo', 'email' => 'encrypted'],
            [],
            new Stack([new TransformMiddleware()]),
        );

        self::assertInstanceOf(SensitiveDataProfileCreated::class, $result);
        self::assertEquals(ProfileId::fromString('foo'), $result->profileId);
        self::assertEquals(new Email('unknown'), $result->email);
    }

    public function testDecryptWithDecryptionFailed(): void
    {
        $cryptographer = $this->createMock(Cryptographer::class);
        $cryptographer->method('supports')->willReturn(true);
        $cryptographer->method('decrypt')->willThrowException(DecryptionFailed::forMethod('aes-256-gcm'));

        $middleware = new CryptographyMiddleware($cryptographer);

        $result = $middleware->hydrate(
            $this->metadata(SensitiveDataProfileCreated::class),
            ['id' => 'foo', 'email' => 'encrypted'],
            [],
            new Stack([new TransformMiddleware()]),
        );

        self::assertInstanceOf(SensitiveDataProfileCreated::class, $result);
        self::assertEquals(ProfileId::fromString('foo'), $result->profileId);
        self::assertEquals(new Email('unknown'), $result->email);
    }

    public function testDecryptWithFallbackCallback(): void
    {
        $cryptographer = $this->createMock(Cryptographer::class);
        $cryptographer->method('supports')->willReturn(true);
        $cryptographer->method('decrypt')->willThrowException(DecryptionFailed::forMethod('aes-256-gcm'));

        $middleware = new CryptographyMiddleware($cryptographer);

        $result = $middleware->hydrate(
            $this->metadata(SensitiveDataProfileCreatedFallbackCallback::class),
            ['id' => 'foo', 'email' => 'encrypted'],
            [],
            new Stack([new TransformMiddleware()]),
        );

        self::assertInstanceOf(SensitiveDataProfileCreatedFallbackCallback::class, $result);
        self::assertEquals(ProfileId::fromString('foo'), $result->profileId);
        self::assertEquals(new Email('foo@example.com'), $result->email);
    }

    public function testDecrypt(): void
    {
        $cryptographer = $this->createMock(Cryptographer::class);
        $cryptographer->method('supports')->willReturn(true);
        $cryptographer->method('decrypt')->willReturn('info@patchlevel.de');

        $middleware = new CryptographyMiddleware($cryptographer);

        $result = $middleware->hydrate(
            $this->metadata(SensitiveDataProfileCreated::class),
            ['id' => 'foo', 'email' => 'encrypted'],
            [],
            new Stack([new TransformMiddleware()]),
        );

        self::assertInstanceOf(SensitiveDataProfileCreated::class, $result);
        self::assertEquals(ProfileId::fromString('foo'), $result->profileId);
        self::assertEquals(Email::fromString('info@patchlevel.de'), $result->email);
    }

    /** @param class-string $class */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = (new AttributeMetadataFactory())->metadata($class);
        (new CryptographyMetadataEnricher())->enrich($metadata);

        return $metadata;
    }
}
