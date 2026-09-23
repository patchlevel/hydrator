<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Store;

use Patchlevel\Hydrator\Extension\Cryptography\Store\CacheKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheKey::class)]
final class CacheKeyTest extends TestCase
{
    public function testForId(): void
    {
        self::assertSame('cipher_key_fecc461840361c0df5a431587f80f2df', CacheKey::forId('key-1'));
    }

    public function testForSubjectId(): void
    {
        self::assertSame('cipher_subject_6252458bc8f7ec63a9231027e513d7aa', CacheKey::forSubjectId('subject-1'));
    }

    public function testForSubjectKeyIds(): void
    {
        self::assertSame('cipher_subject_keys_6252458bc8f7ec63a9231027e513d7aa', CacheKey::forSubjectKeyIds('subject-1'));
    }
}
