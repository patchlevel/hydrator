<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography\Cipher;

use Patchlevel\Hydrator\Cryptography\Cipher\CreateCipherKeyFailed;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\Hydrator\Cryptography\Cipher\CreateCipherKeyFailed */
final class CreateCipherKeyFailedTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = new CreateCipherKeyFailed();

        self::assertSame('Create cipher key failed.', $exception->getMessage());
    }
}
