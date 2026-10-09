# Lifecycle Hooks

Sometimes you need to do something before or after the extract and hydrate
process, for example migrate old data structures, compute derived state or clean up the
result. For this, the `LifecycleExtension` provides four method attributes:
`PreHydrate`, `PostHydrate`, `PreExtract` and `PostExtract`.

## Setup

Register the `LifecycleExtension` on the builder:

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Lifecycle\LifecycleExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new LifecycleExtension())
    ->build();
```

## Hooks

The hooks are **static** methods on the class being hydrated. The data hooks
(`PreHydrate`, `PostExtract`) receive the data array and must return the
(modified) array; the object hooks (`PostHydrate`, `PreExtract`) receive the
object instance.

```php
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PostExtract;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PostHydrate;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PreExtract;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PreHydrate;

final class Profile
{
    public function __construct(
        public string $name,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    #[PreHydrate]
    public static function migrateOldData(array $data, array $context): array
    {
        // rename a legacy field before the object is hydrated
        if (isset($data['profile_name'])) {
            $data['name'] = $data['profile_name'];
            unset($data['profile_name']);
        }

        return $data;
    }

    /** @param array<string, mixed> $context */
    #[PostHydrate]
    public static function afterHydrate(object $object, array $context): void
    {
        // do something with the freshly hydrated object
    }

    /** @param array<string, mixed> $context */
    #[PreExtract]
    public static function beforeExtract(object $object, array $context): void
    {
        // do something with the object before it is extracted
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    #[PostExtract]
    public static function cleanupData(array $data, array $context): array
    {
        // adjust the extracted array before it is returned
        return $data;
    }
}
```
:::warning
The hook methods must be `static`, otherwise a `LogicException` is thrown when
the metadata is created. The object hooks receive the instance as their first
parameter instead of using `$this`.
:::

:::tip
`PreHydrate` is a good place for schema migrations: old stored data can be
upgraded to the current class structure without touching the persisted payload.
:::

## Learn more

* [How extensions and middlewares work](extensions.md)
* [How to use the hydrator](hydrator.md)
* [How to rename fields without hooks](hydrator.md)
