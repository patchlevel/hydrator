# Generated Code

By default the hydrator maps arrays to objects with reflection: the
`ReflectionMapper` iterates over the metadata of a class, sets each
property through a `ReflectionProperty` and calls the [normalizers](normalizer.md)
dynamically. This is fast enough for most applications, but when you hydrate
millions of objects the reflection overhead adds up. The
`GeneratedMapperExtension` uses plain PHP code generated for your classes
instead.

The generated mappers plug in as a [mapper factory](extensions.md#mapper-factories).
The generation and the runtime are strictly separated: a warmup step generates
the code ahead of time, and the hydrator only loads it.

## Enable the extension

Register the `GeneratedMapperExtension` with the directory which contains
the generated code.

```php
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new GeneratedMapperExtension(__DIR__ . '/var/cache/hydrator'))
    ->build();
```
For every class the hydrator looks for a generated mapper in this
directory. Classes without one are mapped with reflection as usual.

:::success
The generated code behaves exactly like the `ReflectionMapper`: objects
are created without calling the constructor, missing fields keep their promoted
default value, private and readonly properties are supported and the same
exceptions are thrown. You can switch between both without changing your
classes.
:::

## Warm up

The `GeneratedMapperWarmer` generates the mappers and writes them to
the directory. Run it in a cache warmer or a deployment step, with the metadata
factory of the builder, so the code matches the metadata of the hydrator,
including the guessers and enrichers of all registered extensions.

```php
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperWarmer;
use Patchlevel\Hydrator\StackHydratorBuilder;

$cachePath = __DIR__ . '/var/cache/hydrator';

$builder = (new StackHydratorBuilder())
    ->useExtension(new GeneratedMapperExtension($cachePath));

$warmer = new GeneratedMapperWarmer($builder->metadataFactory(), $cachePath);
$warmer->warmup([
    ProfileCreated::class,
    NameChanged::class,
]);
```
Classes referenced by these classes, such as nested value objects, get a
mapper too. The warmup throws a `ClassNotGeneratable` exception for a
class which can not be generated, for example an abstract class or a class with
a class normalizer.

:::note
Only the warmer knows how to generate code. At runtime the extension only loads
existing files, the generator is never loaded.
:::

## Changed classes

The name of a generated file contains a fingerprint of everything the code
depends on: the properties with their field names, normalizers, visibility and
default values. If a class changes after the warmup, its generated mapper
is simply not found and the class is mapped with reflection until you warm
up again.

:::warning
A missing warmup does not fail, it only makes the hydration slower. Warm up the
mappers on every deployment, like you do for other generated files.
:::

## Nested objects

The biggest speedup comes from nested objects: instead of going through the
normalizer and the hydrator again for every nested object, the generated code
calls the [mapper](extensions.md#mapper-factories) the hydrator built for the
nested class directly. That mapper already contains the
[middlewares](extensions.md) and the [lazy](lazy.md) proxy of the nested class,
so nothing changes in behaviour. With the [cryptography](cryptography.md)
extension for example, value objects without sensitive data are mapped by their
generated mapper right away, while classes with sensitive data still go
through the middleware, without the detour through the hydrator.

Only classes with a class normalizer are left to the normalizer.

:::note
If a hydrator wraps the `StackHydrator`, nested objects take the way through
the normalizer for its calls, so it sees every nested object.
:::

## Learn more

* [How to build the hydrator](hydrator.md)
* [How to cache the metadata](caching.md)
* [How to write your own extension](extensions.md)
