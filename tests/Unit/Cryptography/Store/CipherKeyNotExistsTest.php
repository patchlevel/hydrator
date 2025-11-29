<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography\Store;

use Patchlevel\Hydrator\Cryptography\Store\CipherKeyNotExists;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CipherKeyNotExists::class)]
final class CipherKeyNotExistsTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = new CipherKeyNotExists('foo');

        self::assertSame('Cipher key with subject id "foo" not found.', $exception->getMessage());
    }
}
