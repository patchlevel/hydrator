<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Store;

use DateTimeImmutable;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CacheKey;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Extension\Cryptography\Store\InMemoryCipherKeyStore;
use Patchlevel\Hydrator\Extension\Cryptography\Store\Psr6CacheStoreDecorator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(Psr6CacheStoreDecorator::class)]
#[CoversClass(CacheKey::class)]
final class Psr6CacheStoreDecoratorTest extends TestCase
{
    public function testCurrentKeyForIsCached(): void
    {
        $key = $this->createKey('key-1', 'subject-1');

        $innerStore = new InMemoryCipherKeyStore();
        $innerStore->store($key);

        $store = new Psr6CacheStoreDecorator($innerStore, new ArrayAdapter());

        self::assertEquals($key, $store->currentKeyFor('subject-1'));

        $innerStore->clear();

        self::assertEquals($key, $store->currentKeyFor('subject-1'));
    }

    public function testGetIsCached(): void
    {
        $key = $this->createKey('key-1', 'subject-1');

        $innerStore = new InMemoryCipherKeyStore();
        $innerStore->store($key);

        $store = new Psr6CacheStoreDecorator($innerStore, new ArrayAdapter());

        self::assertEquals($key, $store->get('key-1'));

        $innerStore->clear();

        self::assertEquals($key, $store->get('key-1'));
    }

    public function testKeysWithReservedCharacters(): void
    {
        $key = $this->createKey('key:{1}', 'user@example.com/1');

        $innerStore = new InMemoryCipherKeyStore();
        $innerStore->store($key);

        $store = new Psr6CacheStoreDecorator($innerStore, new ArrayAdapter());

        self::assertEquals($key, $store->currentKeyFor('user@example.com/1'));
        self::assertEquals($key, $store->get('key:{1}'));
    }

    public function testStoreDelegatesToInnerStore(): void
    {
        $key = $this->createKey('key-1', 'subject-1');

        $innerStore = new InMemoryCipherKeyStore();

        $store = new Psr6CacheStoreDecorator($innerStore, new ArrayAdapter());
        $store->store($key);

        self::assertEquals($key, $innerStore->get('key-1'));
    }

    public function testRemoveEvictsKeyAndSubject(): void
    {
        $innerStore = new InMemoryCipherKeyStore();
        $innerStore->store($this->createKey('key-1', 'subject-1'));

        $store = new Psr6CacheStoreDecorator($innerStore, new ArrayAdapter());
        $store->currentKeyFor('subject-1');
        $store->get('key-1');

        $store->remove('key-1');

        $this->assertKeyIdNotExists($store, 'key-1');
        $this->assertSubjectIdNotExists($store, 'subject-1');
    }

    public function testRemoveUnknownKey(): void
    {
        $innerStore = new InMemoryCipherKeyStore();

        $store = new Psr6CacheStoreDecorator($innerStore, new ArrayAdapter());
        $store->remove('key-1');

        $this->assertKeyIdNotExists($store, 'key-1');
    }

    public function testRemoveWithSubjectIdEvictsAllKeysOfSubject(): void
    {
        $otherKey = $this->createKey('key-3', 'subject-2');

        $innerStore = new InMemoryCipherKeyStore();
        $innerStore->store($this->createKey('key-1', 'subject-1'));
        $innerStore->store($this->createKey('key-2', 'subject-1'));
        $innerStore->store($otherKey);

        $store = new Psr6CacheStoreDecorator($innerStore, new ArrayAdapter());
        $store->get('key-1');
        $store->get('key-2');
        $store->currentKeyFor('subject-1');
        $store->get('key-3');

        $store->removeWithSubjectId('subject-1');

        $this->assertKeyIdNotExists($store, 'key-1');
        $this->assertKeyIdNotExists($store, 'key-2');
        $this->assertSubjectIdNotExists($store, 'subject-1');
        self::assertEquals($otherKey, $store->get('key-3'));
    }

    public function testExpiresAfterIsPassedToCacheItems(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);
        $item->method('set')->willReturnSelf();
        $item->expects(self::exactly(2))->method('expiresAfter')->with(42)->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);
        $cache->expects(self::exactly(2))->method('save')->with($item);

        $innerStore = $this->createMock(CipherKeyStore::class);
        $innerStore->method('get')->willReturn($this->createKey('key-1', 'subject-1'));

        $store = new Psr6CacheStoreDecorator($innerStore, $cache, 42);
        $store->get('key-1');
    }

    private function assertKeyIdNotExists(CipherKeyStore $store, string $id): void
    {
        try {
            $store->get($id);
            self::fail('Expected CipherKeyNotExists for key id ' . $id);
        } catch (CipherKeyNotExists) {
            $this->addToAssertionCount(1);
        }
    }

    private function assertSubjectIdNotExists(CipherKeyStore $store, string $subjectId): void
    {
        try {
            $store->currentKeyFor($subjectId);
            self::fail('Expected CipherKeyNotExists for subject id ' . $subjectId);
        } catch (CipherKeyNotExists) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * @param non-empty-string $id
     * @param non-empty-string $subjectId
     */
    private function createKey(string $id, string $subjectId): CipherKey
    {
        return new CipherKey(
            $id,
            $subjectId,
            'secret',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );
    }
}
