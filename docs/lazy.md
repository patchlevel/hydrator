# Lazy Objects

Since PHP 8.4, it is possible to hydrate objects lazily. The hydrator then
returns a lazy proxy and the actual hydration happens only when the object is
accessed for the first time. This saves work when you hydrate many objects but
only touch a few of them.

## Enable lazy hydration per class

You can define for each class whether you want it to be lazy by using the
`Lazy` attribute.

```php
use Patchlevel\Hydrator\Attribute\Lazy;

#[Lazy]
final readonly class ProfileCreated
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
```
:::note
If you are using a PHP version older than 8.4, the attribute is ignored and the
object is hydrated eagerly.
:::

## Enable lazy hydration by default

Instead of marking every class, you can make lazy hydration the default when
building the hydrator.

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->enableDefaultLazy()
    ->build();
```

Single classes can then opt out again with the attribute:

```php
use Patchlevel\Hydrator\Attribute\Lazy;

#[Lazy(false)]
final readonly class ProfileCreated
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
```
:::tip
[Cryptography](cryptography.md) is very expensive in terms of performance. You can
combine it with lazy objects so the data is only decrypted when you actually
access the object.
:::

## Learn more

* [How to use the hydrator](hydrator.md)
* [How to configure the builder](extensions.md)
* [How to encrypt sensitive data](cryptography.md)
