<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Store;

use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyNotExists;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CipherKeyNotExists::class)]
final class CipherKeyNotExistsTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = CipherKeyNotExists::forSubjectId('foo');

        self::assertStringContainsString('foo', $exception->getMessage());
    }
}
