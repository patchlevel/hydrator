# Generated Code

By default the `ReflectionTransformer` maps arrays to objects with reflection:
it iterates over the metadata of a class, sets each property through a
`ReflectionProperty` and calls the [normalizers](normalizer.md) dynamically.
This is fast enough for most applications, but when you hydrate millions of
objects the reflection overhead adds up. The `GeneratedTransformerExtension`
uses generated plain PHP code instead, one class per hydrated class.

## Enable the extension

Register the `GeneratedTransformerExtension` in addition to the `CoreExtension`.
It needs a directory where the generated transformers are stored.

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new GeneratedTransformerExtension(
        cachePath: __DIR__ . '/var/cache/hydrator',
        autoGenerate: $debug,
    ))
    ->buildHydrator();
```
The extension registers a [transformer factory](extensions.md#transformer-factories),
nothing else changes: all middlewares run as before and the generated code
takes the place of reflection at the end of the stack. If no other middleware
has to run for a class, the hydrator calls the generated code directly.

:::success
The generated code behaves exactly like the `ReflectionTransformer`: objects are
created without calling the constructor, missing fields keep their promoted
default value, private and readonly properties are supported and the same
exceptions are thrown. You can switch between both without changing your
classes.
:::

## Generate the code

With `autoGenerate` the transformer of a class is generated the first time the
class is hydrated or extracted. This is convenient in development, but in
production the cache path is often read-only. There you generate the code
ahead of time, for example when the application is deployed, with the
`TransformerCompiler`. Use the metadata factory of the builder, so the code is
generated with the guessers and enrichers of all extensions.

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerExtension;
use Patchlevel\Hydrator\Extension\Generated\TransformerCompiler;
use Patchlevel\Hydrator\StackHydratorBuilder;

$builder = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new GeneratedTransformerExtension(__DIR__ . '/var/cache/hydrator'));

$compiler = new TransformerCompiler($builder->metadataFactory(), __DIR__ . '/var/cache/hydrator');
$compiler->compile([
    ProfileCreated::class,
    NameChanged::class,
]);
```
Classes referenced by these classes, such as nested value objects, are
discovered and generated automatically.

:::note
Classes without generated code are transformed with reflection. A missing
compile step makes the hydrator slower, but never breaks it.
:::

## Outdated code

The generated code is tied to the [metadata](caching.md) and the definition of
your classes. The name of a generated transformer contains a fingerprint of
the class: its properties, field names, normalizers, visibility and defaults.
If one of them changes, the existing code no longer matches and the class is
transformed with reflection until its code is generated again. Old files are
never used again, you can delete them when you deploy.

## Nested objects

The biggest speedup comes from inlining nested objects: instead of going through
the hydrator again for every nested object, the generated code hydrates and
extracts them directly. Inlining a class would hide it from the
other middlewares, so it only happens when every other middleware in the stack
is a `SkippableMiddleware` which [skips](extensions.md#skipping-middlewares)
that class. The decision is made per class and per direction: with the
[cryptography](cryptography.md) extension for example, value objects without
sensitive data are inlined while classes with sensitive data still go through
the stack.

Inlining would also hide nested objects from [decorators](extensions.md#decorators),
so nothing is inlined as soon as a decorator is registered. With the
[tracing](tracing.md) extension, every nested object goes through the tracer
and shows up in the traces, at the cost of the inlining.

:::note
Nested classes with a class level normalizer, [lazy](lazy.md) classes and
nested classes which changed since the code was generated are never inlined,
they always take the regular path through the hydrator.
:::

## Learn more

* [How to build the hydrator](hydrator.md)
* [How to cache the metadata](caching.md)
* [How to write your own extension](extensions.md)
