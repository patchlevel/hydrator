# Cryptography

The cryptography extension can encrypt and decrypt sensitive data, e.g.
personal data of customers. For each subject (e.g. a person) a separate cipher
key is created and used to encrypt the marked fields. If the key is deleted,
the data becomes unreadable. This pattern is known as crypto-shredding and
makes "forgetting" a person possible even in immutable storage.
:::experimental
The cryptography extension is experimental and may change in a minor release.
:::

## Setup

Register the `CryptographyExtension` on the builder and pass it a
`Cryptographer`. The `BaseCryptographer` with the openssl cipher is the default
choice; it needs a [cipher key store](#cipher-key-store) to keep the keys.

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Cryptography\BaseCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyExtension;
use Patchlevel\Hydrator\Extension\Cryptography\Store\InMemoryCipherKeyStore;
use Patchlevel\Hydrator\StackHydratorBuilder;

$cipherKeyStore = new InMemoryCipherKeyStore();

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new CryptographyExtension(BaseCryptographer::createWithOpenssl($cipherKeyStore)))
    ->build();
```

## DataSubjectId

First you need to define which field identifies the subject the data belongs
to. The cipher key is created and looked up per subject id.

```php
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

final class EmailChanged
{
    public function __construct(
        #[DataSubjectId]
        public readonly string $profileId,
        #[SensitiveData]
        public readonly string|null $email,
    ) {
    }
}
```
:::warning
The `DataSubjectId` must be a string, you can use a [normalizer](normalizer.md)
to convert a value object to a string. The subject id itself cannot be sensitive
data.
:::

You can also use multiple subject ids in one class by naming them and
referencing the name from the sensitive fields. The default name is `default`.

```php
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

final class ProfilesMerged
{
    public function __construct(
        #[DataSubjectId(name: 'source')]
        public readonly string $sourceProfileId,
        #[SensitiveData(subjectIdName: 'source')]
        public readonly string|null $sourceEmail,
        #[DataSubjectId(name: 'target')]
        public readonly string $targetProfileId,
        #[SensitiveData(subjectIdName: 'target')]
        public readonly string|null $targetEmail,
    ) {
    }
}
```

## Fallback values

If the data could not be decrypted, because the key has been removed, a
fallback value is inserted. The default fallback is `null`. You can change this
with the `fallback` parameter:

```php
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

final class ProfileCreated
{
    public function __construct(
        #[DataSubjectId]
        public readonly string $profileId,
        #[SensitiveData(fallback: 'unknown')]
        public readonly string $name,
    ) {
    }
}
```

You can also use a callable as a fallback. It receives the subject id:

```php
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

final class ProfileCreated
{
    public function __construct(
        #[DataSubjectId]
        public readonly string $profileId,
        #[SensitiveData(fallback: 'deleted profile')]
        public readonly string $name,
        #[SensitiveData(fallbackCallable: [self::class, 'anonymizedEmail'])]
        public readonly string $email,
    ) {
    }

    public static function anonymizedEmail(string $subjectId): string
    {
        return sprintf('%s@anonymized.example', $subjectId);
    }
}
```
:::note
`fallback` and `fallbackCallable` are mutually exclusive, setting both throws
an exception.
:::

## Cipher Key Store

The cipher keys must be stored somewhere. For testing purposes there is an
in-memory implementation:

```php
use Patchlevel\Hydrator\Extension\Cryptography\Store\InMemoryCipherKeyStore;

$cipherKeyStore = new InMemoryCipherKeyStore();
```

For production you have to implement the `CipherKeyStore` interface yourself,
backed by a database or a key management service, because only you know where
the keys should live:

```php
namespace Patchlevel\Hydrator\Extension\Cryptography\Store;

use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKey;

interface CipherKeyStore
{
    /** @throws CipherKeyNotExists */
    public function currentKeyFor(string $subjectId): CipherKey;

    /** @throws CipherKeyNotExists */
    public function get(string $id): CipherKey;

    public function store(CipherKey $key): void;

    public function remove(string $id): void;

    public function removeWithSubjectId(string $subjectId): void;
}
```

To avoid hitting your key storage for every operation, you can wrap the store
in one of the cache decorators:

```php
use Patchlevel\Hydrator\Extension\Cryptography\Store\Psr6CacheStoreDecorator;
use Patchlevel\Hydrator\Extension\Cryptography\Store\Psr16CacheStoreDecorator;

$cipherKeyStore = new Psr6CacheStoreDecorator($myDatabaseStore, $psr6CachePool);
// or
$cipherKeyStore = new Psr16CacheStoreDecorator($myDatabaseStore, $psr16Cache);
```

## Remove personal data

To remove personal data, you only need to remove the keys for the subject from
the store. All encrypted fields of that subject then resolve to their
[fallback values](#fallback-values).

```php
$cipherKeyStore->removeWithSubjectId('profile-1');
```
:::danger
Removing a cipher key is irreversible. The encrypted data can never be decrypted
again, that is the point of crypto-shredding, but make sure it is what you want.
:::

:::tip
Cryptography is very expensive in terms of performance. You can combine it with
[lazy objects](lazy.md) so the data is only decrypted when the object is
actually accessed.
:::

## Learn more

* [How to hydrate objects lazily](lazy.md)
* [How extensions work](extensions.md)
* [How to use the hydrator](hydrator.md)
