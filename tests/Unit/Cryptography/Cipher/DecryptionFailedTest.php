<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography\Cipher;

use Patchlevel\Hydrator\Cryptography\Cipher\DecryptionFailed;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\Hydrator\Cryptography\Cipher\DecryptionFailed */
final class DecryptionFailedTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = new DecryptionFailed();

        self::assertSame('Decryption failed.', $exception->getMessage());
    }
}
