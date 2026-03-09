<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Cipher;

use Patchlevel\Hydrator\Extension\Cryptography\Cipher\EncryptionFailed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EncryptionFailed::class)]
final class EncryptionFailedTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = EncryptionFailed::forMethod('aes-256-gcm');

        self::assertStringContainsString('aes-256-gcm', $exception->getMessage());
    }
}
