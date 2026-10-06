# Generated Code

By default the `ReflectionTransformer` maps arrays to objects with reflection:
it iterates over the metadata of a class, sets each property through a
`ReflectionProperty` and calls the [normalizers](normalizer.md) dynamically.
This is fast enough for most applications, but when you hydrate millions of
objects the reflection overhead adds up. The `GeneratedTransformerExtension`
uses generated plain PHP code instead, one class per hydrated class.

## Enable the extension

Register the `GeneratedTransformerExtension` with the directory where the
generated transformers are stored.

```php
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new GeneratedTransformerExtension(__DIR__ . '/var/cache/hydrator'))
    ->build();
```
The extension registers a [transformer factory](extensions.md#transformer-factories),
nothing else changes: all middlewares run as before and the generated code
takes the place of reflection at the end of the stack. If no middleware has to
run for a class, the hydrator calls the generated code directly.

:::success
The generated code behaves exactly like the `ReflectionTransformer`: objects are
created without calling the constructor, missing fields keep their promoted
default value, private and readonly properties are supported and the same
exceptions are thrown. You can switch between both without changing your
classes.
:::

## Warmup

Code is never generated while the hydrator is used. The extension only loads
transformers which already exist, so the cache path can be read-only at
runtime. Generate them ahead of time with the `GeneratedTransformerWarmer`, for
example when the application is deployed. Pass the hydrator itself, so the code
is generated with the guessers and enrichers of all extensions.

```php
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerWarmer;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new GeneratedTransformerExtension(__DIR__ . '/var/cache/hydrator'))
    ->build();

$warmer = new GeneratedTransformerWarmer($hydrator, __DIR__ . '/var/cache/hydrator');
$warmer->warmup([
    ProfileCreated::class,
    NameChanged::class,
]);
```
Classes referenced by these classes, such as nested value objects, are
discovered and generated automatically.

:::note
Classes without generated code are transformed with reflection. A missing
warmup makes the hydrator slower, but never breaks it.
:::

## Outdated code

The generated code is tied to the [metadata](caching.md) and the definition of
your classes. The name of a generated transformer contains a fingerprint of
the class and its nested classes: their properties, field names, normalizers,
visibility and defaults. If one of them changes, the existing code no longer
matches and the class is transformed with reflection until the warmup runs
again. Old files are never used again, you can delete them when you deploy.

## Nested objects

The biggest speedup comes from inlining nested objects: instead of going through
the hydrator again for every nested object, the generated code hydrates and
extracts them directly. Inlining a class would hide it from the middlewares,
so it only happens when every middleware is a `SkippableMiddleware` which
[skips](extensions.md#skipping-middlewares) that class. The decision is made
per class and per direction: with the [cryptography](cryptography.md) extension
for example, value objects without sensitive data are inlined while classes
with sensitive data still go through the stack.

:::note
Nested classes with a class level normalizer, [lazy](lazy.md) classes and
nested classes which changed since the warmup are never inlined, they always
take the regular path through the hydrator. The same applies if you wrap the
hydrator in your own `Hydrator`, so your wrapper also sees nested objects.
:::

## Learn more

* [How to build the hydrator](hydrator.md)
* [How to cache the metadata](caching.md)
* [How to write your own extension](extensions.md)
