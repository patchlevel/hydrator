<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography\Cipher;

use Patchlevel\Hydrator\Cryptography\Cipher\EncryptionFailed;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\Hydrator\Cryptography\Cipher\EncryptionFailed */
final class EncryptionFailedTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = new EncryptionFailed();

        self::assertSame('Encryption failed.', $exception->getMessage());
    }
}
