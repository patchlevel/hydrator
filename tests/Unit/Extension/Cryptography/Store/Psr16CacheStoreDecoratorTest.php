<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Store;

use DateTimeImmutable;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Extension\Cryptography\Store\Psr16CacheStoreDecorator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(Psr16CacheStoreDecorator::class)]
final class Psr16CacheStoreDecoratorTest extends TestCase
{
    public function testCurrentKeyForWithCacheHit(): void
    {
        $key = $this->createKey();

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->with('subjectId:subject-1')->willReturn($key);
        $cache->expects(self::never())->method('set');

        $innerStore = $this->createMock(CipherKeyStore::class);
        $innerStore->expects(self::never())->method('currentKeyFor');

        $store = new Psr16CacheStoreDecorator($innerStore, $cache);

        self::assertSame($key, $store->currentKeyFor('subject-1'));
    }

    public function testCurrentKeyForWithCacheMiss(): void
    {
        $key = $this->createKey();

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->with('subjectId:subject-1')->willReturn(null);
        $cache->expects(self::once())->method('set')->with('subjectId:subject-1', $key, 42);

        $innerStore = $this->createMock(CipherKeyStore::class);
        $innerStore->expects(self::once())->method('currentKeyFor')->with('subject-1')->willReturn($key);

        $store = new Psr16CacheStoreDecorator($innerStore, $cache, 42);

        self::assertSame($key, $store->currentKeyFor('subject-1'));
    }

    public function testGetWithCacheMiss(): void
    {
        $key = $this->createKey();

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->with('id:key-1')->willReturn(false);
        $cache->expects(self::once())->method('set')->with('id:key-1', $key, null);

        $innerStore = $this->createMock(CipherKeyStore::class);
        $innerStore->expects(self::once())->method('get')->with('key-1')->willReturn($key);

        $store = new Psr16CacheStoreDecorator($innerStore, $cache);

        self::assertSame($key, $store->get('key-1'));
    }

    public function testStoreWritesInnerStoreAndBothCacheEntries(): void
    {
        $key = $this->createKey();

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::exactly(2))->method('set')->willReturnMap([
            ['id:key-1', $key, 17, true],
            ['subjectId:subject-1', $key, 17, true],
        ]);

        $innerStore = $this->createMock(CipherKeyStore::class);
        $innerStore->expects(self::once())->method('store')->with($key);

        $store = new Psr16CacheStoreDecorator($innerStore, $cache, 17);
        $store->store($key);
    }

    public function testRemoveDeletesIdCacheEntry(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('delete')->with('id:key-1');

        $innerStore = $this->createMock(CipherKeyStore::class);
        $innerStore->expects(self::once())->method('remove')->with('key-1');

        $store = new Psr16CacheStoreDecorator($innerStore, $cache);
        $store->remove('key-1');
    }

    public function testRemoveWithSubjectIdDeletesSubjectCacheEntry(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('delete')->with('subjectId:subject-1');

        $innerStore = $this->createMock(CipherKeyStore::class);
        $innerStore->expects(self::once())->method('removeWithSubjectId')->with('subject-1');

        $store = new Psr16CacheStoreDecorator($innerStore, $cache);
        $store->removeWithSubjectId('subject-1');
    }

    private function createKey(): CipherKey
    {
        return new CipherKey(
            'key-1',
            'subject-1',
            'secret',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );
    }
}
