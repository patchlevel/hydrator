<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Cipher;

use Patchlevel\Hydrator\Extension\Cryptography\Cipher\DecryptionFailed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DecryptionFailed::class)]
final class DecryptionFailedTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = new DecryptionFailed();

        self::assertSame('Decryption failed.', $exception->getMessage());
    }
}
