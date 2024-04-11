<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography\Store;

use Patchlevel\Hydrator\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Cryptography\Store\InMemoryCipherKeyStore;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\Hydrator\Cryptography\Store\InMemoryCipherKeyStore */
final class InMemoryCipherKeyStoreTest extends TestCase
{
    public function testStoreAndLoad(): void
    {
        $key = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $store = new InMemoryCipherKeyStore();
        $store->store('foo', $key);

        self::assertSame($key, $store->get('foo'));
    }

    public function testLoadFailed(): void
    {
        $this->expectException(CipherKeyNotExists::class);

        $store = new InMemoryCipherKeyStore();
        $store->get('foo');
    }

    public function testRemove(): void
    {
        $key = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $store = new InMemoryCipherKeyStore();
        $store->store('foo', $key);

        self::assertSame($key, $store->get('foo'));

        $store->remove('foo');

        $this->expectException(CipherKeyNotExists::class);

        $store->get('foo');
    }

    public function testClear(): void
    {
        $key = new CipherKey(
            'foo',
            'bar',
            'baz',
        );

        $store = new InMemoryCipherKeyStore();
        $store->store('foo', $key);

        self::assertSame($key, $store->get('foo'));

        $store->clear();

        $this->expectException(CipherKeyNotExists::class);

        $store->get('foo');
    }
}
