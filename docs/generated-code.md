# Generated Code

The `TransformMiddleware` maps arrays to objects with reflection: it iterates
over the metadata of a class, sets each property through a `ReflectionProperty`
and calls the [normalizers](normalizer.md) dynamically. This is fast enough for
most applications, but when you hydrate millions of objects the reflection
overhead adds up. The `GeneratedMiddlewareExtension` generates plain PHP code
for a fixed set of classes, which is then used instead of the reflection based
mapping for exactly these classes.

## Enable the extension

Register the `GeneratedMiddlewareExtension` in addition to the `CoreExtension`.
It needs a writable directory for the generated file and the list of classes
for which code should be generated. Classes referenced by these classes, such
as nested value objects, are discovered automatically.

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMiddlewareExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new GeneratedMiddlewareExtension(
        cachePath: __DIR__ . '/var/cache/hydrator',
        classes: [
            ProfileCreated::class,
            NameChanged::class,
        ],
    ))
    ->build();
```
:::note
The builder returns a `GeneratedHydrator` instead of the plain `StackHydrator`.
It is a `StackHydrator` in every respect, but for the generated classes it
calls the generated code directly and skips the middleware stack entirely, as
long as no other middleware has to run for the class (see
[Nested objects](#nested-objects) for how that is decided). Everything else
takes the regular path.
:::

:::success
The generated code behaves exactly like the `TransformMiddleware`: objects are
created without calling the constructor, missing fields keep their promoted
default value, private and readonly properties are supported and the same
exceptions are thrown. You can switch between both without changing your
classes.
:::

The generated middleware runs right before the `TransformMiddleware` of the
`CoreExtension`, regardless of the registration order. Classes which are not
part of the list are passed on and handled by the `TransformMiddleware` as
usual.

## Caching

The generated middleware is written to the cache path once and reused on
subsequent requests, so the generation only happens on the first run. The file
name is derived from the configuration, changing the class list or the options
results in a new file. In development you can regenerate the code on every
build with the `debug` flag.

```php
use Patchlevel\Hydrator\Extension\Generated\GeneratedMiddlewareExtension;

$extension = new GeneratedMiddlewareExtension(
    cachePath: __DIR__ . '/var/cache/hydrator',
    classes: [ProfileCreated::class],
    debug: true,
);
```
:::warning
The generated code is tied to the [metadata](caching.md) of your classes. If a
field is renamed or a normalizer is added after the code was generated, the
middleware throws an `OutdatedGeneratedMiddleware` exception. Clear the cache
path when you deploy, like you do for other generated files.
:::

## Nested objects

The biggest speedup comes from inlining nested objects: instead of going through
the whole middleware stack again for every nested object, the generated code
hydrates and extracts them directly. Inlining a class would hide it from the
other middlewares, so it only happens when every other middleware in the stack
is a `SkippableMiddleware` which [skips](extensions.md) that class. The decision
is made per class and per direction: with the
[cryptography](cryptography.md) extension for example, value objects without
sensitive data are inlined while classes with sensitive data still go through
the stack.

:::note
Nested classes with a class level normalizer or [lazy](lazy.md) classes are
never inlined, they always take the regular path through the hydrator. The
generated middleware is itself skippable: classes it has no code for go
straight to the `TransformMiddleware`.
:::

## Learn more

* [How to build the hydrator](hydrator.md)
* [How to cache the metadata](caching.md)
* [How to write your own extension](extensions.md)
