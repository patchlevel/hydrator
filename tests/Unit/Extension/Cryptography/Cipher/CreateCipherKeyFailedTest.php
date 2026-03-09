<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Cipher;

use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CreateCipherKeyFailed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateCipherKeyFailed::class)]
final class CreateCipherKeyFailedTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = CreateCipherKeyFailed::forMethod('aes-256-gcm', 'test reason');

        self::assertStringContainsString('aes-256-gcm', $exception->getMessage());
        self::assertStringContainsString('test reason', $exception->getMessage());
    }
}
