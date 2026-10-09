# Caching

Before the hydrator can process a class, it builds metadata for it: the
properties, their field names and the resolved [normalizers](normalizer.md).
This happens once per class and process and is cheap, but with many classes
(or in short-lived processes) you can cache the metadata with any PSR-6 or
PSR-16 cache to skip the reflection entirely.

## Configure the cache

Pass the cache to the builder with `setCache`. Both PSR-6
(`Psr\Cache\CacheItemPoolInterface`) and PSR-16 (`Psr\SimpleCache\CacheInterface`)
implementations are accepted.

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->setCache(new FilesystemAdapter())
    ->build();
```
:::note
Internally the builder wraps the metadata factory in a `Psr6MetadataFactory` or
`Psr16MetadataFactory` from the `Patchlevel\Hydrator\Metadata` namespace. You can
also use these decorators directly if you construct the `StackHydrator` by hand.
:::

:::warning
The cached metadata contains the resolved normalizer instances and everything
metadata enrichers stored in `extras`, so all of it must be serializable. Clear
the cache when you change attributes, property types or normalizers, stale
metadata leads to confusing results.
:::

## Learn more

* [How metadata enrichers add data to the metadata](extensions.md)
* [How normalizers are resolved](normalizer.md)
* [How to use the hydrator](hydrator.md)
