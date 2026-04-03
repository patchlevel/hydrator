<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Store;

use DateTimeImmutable;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Extension\Cryptography\Store\Psr6CacheStoreDecorator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

#[CoversClass(Psr6CacheStoreDecorator::class)]
final class Psr6CacheStoreDecoratorTest extends TestCase
{
    public function testCurrentKeyForWithCacheHit(): void
    {
        $key = $this->createKey();

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects(self::once())->method('get')->willReturn($key);
        $item->expects(self::once())->method('isHit')->willReturn(true);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects(self::once())->method('getItem')->with('subjectId:subject-1')->willReturn($item);

        $innerStore = $this->createMock(CipherKeyStore::class);
        $innerStore->expects(self::never())->method('currentKeyFor');

        $store = new Psr6CacheStoreDecorator($innerStore, $cache);

        self::assertSame($key, $store->currentKeyFor('subject-1'));
    }

    public function testCurrentKeyForWithCacheMiss(): void
    {
        $key = $this->createKey();

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects(self::once())->method('get')->willReturn(null);
        $item->expects(self::once())->method('isHit')->willReturn(false);
        $item->expects(self::once())->method('set')->with($key)->willReturnSelf();
        $item->expects(self::once())->method('expiresAfter')->with(42)->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects(self::once())->method('getItem')->with('subjectId:subject-1')->willReturn($item);
        $cache->expects(self::once())->method('save')->with($item);

        $innerStore = $this->createMock(CipherKeyStore::class);
        $innerStore->expects(self::once())->method('currentKeyFor')->with('subject-1')->willReturn($key);

        $store = new Psr6CacheStoreDecorator($innerStore, $cache, 42);

        self::assertSame($key, $store->currentKeyFor('subject-1'));
    }

    public function testGetWithCacheMiss(): void
    {
        $key = $this->createKey();

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects(self::once())->method('get')->willReturn(null);
        $item->expects(self::once())->method('isHit')->willReturn(false);
        $item->expects(self::once())->method('set')->with($key)->willReturnSelf();
        $item->expects(self::once())->method('expiresAfter')->with(null)->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects(self::once())->method('getItem')->with('id:key-1')->willReturn($item);
        $cache->expects(self::once())->method('save')->with($item);

        $innerStore = $this->createMock(CipherKeyStore::class);
        $innerStore->expects(self::once())->method('get')->with('key-1')->willReturn($key);

        $store = new Psr6CacheStoreDecorator($innerStore, $cache);

        self::assertSame($key, $store->get('key-1'));
    }

    public function testStoreDelegatesToInnerStore(): void
    {
        $key = $this->createKey();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects(self::never())->method('getItem');
        $cache->expects(self::never())->method('save');

        $innerStore = $this->createMock(CipherKeyStore::class);
        $innerStore->expects(self::once())->method('store')->with($key);

        $store = new Psr6CacheStoreDecorator($innerStore, $cache);
        $store->store($key);
    }

    public function testRemoveDeletesIdCacheEntry(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects(self::once())->method('deleteItem')->with('id:key-1');

        $innerStore = $this->createMock(CipherKeyStore::class);
        $innerStore->expects(self::once())->method('remove')->with('key-1');

        $store = new Psr6CacheStoreDecorator($innerStore, $cache);
        $store->remove('key-1');
    }

    public function testRemoveWithSubjectIdDeletesSubjectCacheEntry(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects(self::once())->method('deleteItem')->with('subjectId:subject-1');

        $innerStore = $this->createMock(CipherKeyStore::class);
        $innerStore->expects(self::once())->method('removeWithSubjectId')->with('subject-1');

        $store = new Psr6CacheStoreDecorator($innerStore, $cache);
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
