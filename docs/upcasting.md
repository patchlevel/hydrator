# Upcasting

Over time the shape of your stored data drifts away from your classes: fields
get renamed, split or merged. Upcasting reshapes the stored array on the fly
while it is hydrated, so old payloads keep loading into your current classes
without a migration of the underlying storage.

## Setup

Register the `UpcastExtension` on the builder and pass it a list of upcasters.
Each upcaster receives the raw data array and returns a reshaped array.

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;
use Patchlevel\Hydrator\Extension\Upcast\UpcastExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new UpcastExtension(
        beforeTransform: [
            CallbackUpcaster::forClass(
                ProfileCreated::class,
                static function (array $data): array {
                    $data['name'] = $data['firstName'] . ' ' . $data['lastName'];
                    unset($data['firstName'], $data['lastName']);

                    return $data;
                },
            ),
        ],
    ))
    ->build();
```
:::note
Upcasting only runs during [hydration](hydrator.md). Extraction always writes
the current shape, so once an object has been re-extracted its stored payload is
up to date.
:::

## Writing an upcaster

An upcaster implements the `Upcaster` interface. It receives the
[class metadata](hydrator.md), the data array and the context, and returns the
reshaped data. Because every registered upcaster runs for every class, check the
metadata and leave data you do not care about untouched.

```php
use Patchlevel\Hydrator\Extension\Upcast\Upcaster;
use Patchlevel\Hydrator\Metadata\ClassMetadata;

final class RenameEmailUpcaster implements Upcaster
{
    public function upcast(ClassMetadata $metadata, array $data, array $context): array
    {
        if ($metadata->className !== ProfileCreated::class) {
            return $data;
        }

        $data['email'] = $data['mail'];
        unset($data['mail']);

        return $data;
    }
}
```

For the common case of a single class and a closure, use the
`CallbackUpcaster`. It compares the class name for you and only invokes the
callback for a match. The callback receives the data and the context:

```php
use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;

$upcaster = CallbackUpcaster::forClass(
    ProfileCreated::class,
    static function (array $data, array $context): array {
        $data['email'] = $data['mail'];
        unset($data['mail']);

        return $data;
    },
);
```

## When upcasters run

The hydrator decodes the stored payload in stages: first it is read as raw
values, then [normalizers](normalizer.md) decode each field, and finally the
object is built. The `UpcastExtension` can hook into two of these stages, and
you pass your upcasters to the matching argument.

| Argument | Runs | Works on |
| --- | --- | --- |
| `beforeEncoding` | before the values are decoded | the raw stored values (strings, ints, ...) |
| `beforeTransform` | after decoding, right before the object is built | the decoded values (enums, dates, value objects, ...) |

Use `beforeEncoding` when you rename or restructure fields whose raw form is
enough, and `beforeTransform` when you need the already decoded values.

```php
use Patchlevel\Hydrator\Extension\Upcast\UpcastExtension;

$extension = new UpcastExtension(
    beforeEncoding: [$renameFieldUpcaster],
    beforeTransform: [$mergeNameUpcaster],
);
```
:::warning
The [cryptography](cryptography.md) extension decrypts values during the
decoding stage. A `beforeEncoding` upcaster therefore still sees the encrypted
values, while a `beforeTransform` upcaster sees the decrypted ones. Pick the
stage that matches the data you need.
:::

## Learn more

* [How to write your own extension](extensions.md)
* [How to decode values with normalizers](normalizer.md)
* [How to encrypt sensitive data](cryptography.md)
