<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography;

use Patchlevel\Hydrator\Cryptography\CryptographyMiddleware;
use Patchlevel\Hydrator\Cryptography\PayloadCryptographer;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

#[CoversClass(CryptographyMiddleware::class)]
final class CryptographyMiddlewareTest extends TestCase
{
    public function testHydrate(): void
    {
        $metadata = new ClassMetadata(new ReflectionClass(stdClass::class));

        $payloadCryptographer = $this->createMock(PayloadCryptographer::class);
        $payloadCryptographer->expects($this->once())->method('decrypt')->with($metadata, ['name' => 'foo'])->willReturn(['name' => 'bar']);

        $object = new stdClass();

        $cryptographyMiddleware = new CryptographyMiddleware($payloadCryptographer);

        $otherMiddleware = $this->createMock(Middleware::class);

        $stack = new Stack([$otherMiddleware]);

        $otherMiddleware->expects($this->once())->method('hydrate')->with($metadata, ['name' => 'bar'], [], $stack)->willReturn($object);

        $result = $cryptographyMiddleware->hydrate($metadata, ['name' => 'foo'], [], $stack);

        self::assertSame($object, $result);
    }

    public function testExtract(): void
    {
        $metadata = new ClassMetadata(new ReflectionClass(stdClass::class));

        $payloadCryptographer = $this->createMock(PayloadCryptographer::class);
        $payloadCryptographer->expects($this->once())->method('encrypt')->with($metadata, ['name' => 'foo'])->willReturn(['name' => 'bar']);

        $object = new stdClass();

        $cryptographyMiddleware = new CryptographyMiddleware($payloadCryptographer);

        $otherMiddleware = $this->createMock(Middleware::class);

        $stack = new Stack([$otherMiddleware]);

        $otherMiddleware->expects($this->once())->method('extract')->with($metadata, $object, [], $stack)->willReturn(['name' => 'foo']);

        $result = $cryptographyMiddleware->extract($metadata, $object, [], $stack);

        self::assertSame(['name' => 'bar'], $result);
    }
}
