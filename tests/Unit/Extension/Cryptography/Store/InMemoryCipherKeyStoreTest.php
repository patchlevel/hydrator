<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Store;

use DateTimeImmutable;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Extension\Cryptography\Store\InMemoryCipherKeyStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryCipherKeyStore::class)]
final class InMemoryCipherKeyStoreTest extends TestCase
{
    public function testStoreAndLoad(): void
    {
        $key = new CipherKey(
            'key-1',
            'subject-1',
            'secret',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );

        $store = new InMemoryCipherKeyStore();
        $store->store($key);

        self::assertSame($key, $store->get('key-1'));
    }

    public function testLoadFailed(): void
    {
        $this->expectException(CipherKeyNotExists::class);

        $store = new InMemoryCipherKeyStore();
        $store->get('non-existent');
    }

    public function testRemove(): void
    {
        $key = new CipherKey(
            'key-1',
            'subject-1',
            'secret',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );

        $store = new InMemoryCipherKeyStore();
        $store->store($key);

        self::assertSame($key, $store->get('key-1'));

        $store->remove('key-1');

        $this->expectException(CipherKeyNotExists::class);

        $store->get('key-1');
    }

    public function testClear(): void
    {
        $key = new CipherKey(
            'key-1',
            'subject-1',
            'secret',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );

        $store = new InMemoryCipherKeyStore();
        $store->store($key);

        self::assertSame($key, $store->get('key-1'));

        $store->clear();

        $this->expectException(CipherKeyNotExists::class);

        $store->get('key-1');
    }

    public function testCurrentKeyFor(): void
    {
        $key1 = new CipherKey(
            'key-1',
            'subject-1',
            'secret-1',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );

        $key2 = new CipherKey(
            'key-2',
            'subject-1',
            'secret-2',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );

        $store = new InMemoryCipherKeyStore();
        $store->store($key1);
        $store->store($key2);

        self::assertSame($key2, $store->currentKeyFor('subject-1'));
    }

    public function testCurrentKeyForNotExists(): void
    {
        $this->expectException(CipherKeyNotExists::class);

        $store = new InMemoryCipherKeyStore();
        $store->currentKeyFor('non-existent');
    }

    public function testRemoveWithSubjectId(): void
    {
        $key1 = new CipherKey(
            'key-1',
            'subject-1',
            'secret-1',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );

        $key2 = new CipherKey(
            'key-2',
            'subject-1',
            'secret-2',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );

        $key3 = new CipherKey(
            'key-3',
            'subject-2',
            'secret-3',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );

        $store = new InMemoryCipherKeyStore();
        $store->store($key1);
        $store->store($key2);
        $store->store($key3);

        $store->removeWithSubjectId('subject-1');

        $this->expectException(CipherKeyNotExists::class);
        $store->get('key-1');
    }

    public function testRemoveWithSubjectIdDoesNotAffectOtherSubjects(): void
    {
        $key1 = new CipherKey(
            'key-1',
            'subject-1',
            'secret-1',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );

        $key2 = new CipherKey(
            'key-2',
            'subject-2',
            'secret-2',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );

        $store = new InMemoryCipherKeyStore();
        $store->store($key1);
        $store->store($key2);

        $store->removeWithSubjectId('subject-1');

        self::assertSame($key2, $store->get('key-2'));
    }

    public function testRemoveWithSubjectIdNonExistent(): void
    {
        $store = new InMemoryCipherKeyStore();
        $store->removeWithSubjectId('non-existent');

        $this->expectNotToPerformAssertions();
    }

    public function testRemoveNonExistent(): void
    {
        $store = new InMemoryCipherKeyStore();
        $store->remove('non-existent');

        $this->expectNotToPerformAssertions();
    }

    public function testStoreOverwritesExistingKey(): void
    {
        $key1 = new CipherKey(
            'key-1',
            'subject-1',
            'secret-1',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );

        $key2 = new CipherKey(
            'key-1',
            'subject-1',
            'secret-2',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );

        $store = new InMemoryCipherKeyStore();
        $store->store($key1);
        $store->store($key2);

        self::assertSame($key2, $store->get('key-1'));
        self::assertSame($key2, $store->currentKeyFor('subject-1'));
    }

    public function testMultipleSubjects(): void
    {
        $key1 = new CipherKey(
            'key-1',
            'subject-1',
            'secret-1',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );

        $key2 = new CipherKey(
            'key-2',
            'subject-2',
            'secret-2',
            'aes-256-gcm',
            new DateTimeImmutable(),
        );

        $store = new InMemoryCipherKeyStore();
        $store->store($key1);
        $store->store($key2);

        self::assertSame($key1, $store->currentKeyFor('subject-1'));
        self::assertSame($key2, $store->currentKeyFor('subject-2'));
    }
}
