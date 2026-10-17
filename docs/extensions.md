# Extensions

The `StackHydrator` is assembled from small building blocks: middlewares that
wrap the hydration process, [guessers](guesser.md) that resolve normalizers,
metadata enrichers that add information to the class metadata and mapper
factories that map the data to the object. An extension bundles such building
blocks so they can be registered with a single call.

## Using extensions

Extensions are registered on the `StackHydratorBuilder` with `useExtension`.
The default behaviour, the property mapping and the
[built-in guesser](guesser.md#built-in-guesser), is always there, you don't
have to register anything for it.

```php
use Patchlevel\Hydrator\Extension\Lifecycle\LifecycleExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new LifecycleExtension())
    ->build();
```
## Built-in extensions

The library ships with four extensions out of the box:

| Extension | Purpose |
| --- | --- |
| `LifecycleExtension` | [Lifecycle hooks](lifecycle-hooks.md), run code before and after the extract and hydrate process. |
| `CryptographyExtension` | [Cryptography](cryptography.md), encrypt and decrypt sensitive data with crypto-shredding. |
| `UpcastExtension` | [Upcasting](upcasting.md), reshape outdated stored data while it is hydrated. |
| `GeneratedMapperExtension` | [Generated code](generated-code.md), use generated mapping code instead of reflection. |

## Middleware

A middleware wraps the [mapper](#mapper-factories) of a class, similar to HTTP
middlewares. It can modify the incoming data, the outgoing array or the object
itself, and passes the call on with `$next`. `$next` is a `Mapper` too: the
following middlewares and at the end the mapper of the class, which does the
actual property mapping. The hydrator builds this chain once per class.

```php
use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;

final class RemoveNullValuesMiddleware implements Middleware
{
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Mapper $next): object
    {
        return $next->hydrate($data, $context);
    }

    public function extract(ClassMetadata $metadata, object $object, array $context, Mapper $next): array
    {
        $data = $next->extract($object, $context);

        return array_filter($data, static fn (mixed $value) => $value !== null);
    }
}
```
Middlewares are added with a priority, higher priorities run first (outermost).
The mapper of the class always runs last, after all middlewares.

```php
$builder->addMiddleware(new RemoveNullValuesMiddleware(), 0);
```
## Skipping middlewares

A middleware usually only has something to do for a few classes. Instead of
being part of the chain of every class, a middleware can implement
`SkippableMiddleware` and tell the hydrator that it is not needed for a class.
The decision is made once per class and then reused, so it must only depend on
the metadata.

The `skip` method returns a `Skip` case: `Skip::None` to always run,
`Skip::Hydrate` or `Skip::Extract` to be left out in one direction only and
`Skip::Both` to be left out completely.

```php
use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;

final class RemoveNullValuesMiddleware implements SkippableMiddleware
{
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Mapper $next): object
    {
        return $next->hydrate($data, $context);
    }

    public function extract(ClassMetadata $metadata, object $object, array $context, Mapper $next): array
    {
        $data = $next->extract($object, $context);

        return array_filter($data, static fn (mixed $value) => $value !== null);
    }

    public function skip(ClassMetadata $metadata): Skip
    {
        // the middleware only does something while extracting
        return Skip::Hydrate;
    }
}
```
The built-in middlewares use this as well: the `CryptographyMiddleware` is left
out for classes without sensitive data, the `LifecycleMiddleware` only runs in
the directions the class has hooks for, and the `UpcastMiddleware` is left out
while extracting, since upcasting only ever happens while hydrating. If every
upcaster is a `CallbackUpcaster` or carries an
[`#[UpcasterFor]`](upcasting.md#writing-an-upcaster) attribute, it is also left
out while hydrating classes none of them target.

:::tip
If every middleware skips a class, the mapper of the class is the whole chain
and the hydrator calls it directly.
:::

## Metadata enricher

A metadata enricher runs once per class when the metadata is created. It can
inspect the class and attach extra information to `ClassMetadata::$extras`,
which a middleware can later read. This keeps expensive reflection out of the
hot path.

```php
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;

final class AuditMetadataEnricher implements MetadataEnricher
{
    public function enrich(ClassMetadata $classMetadata): void
    {
        $attributes = $classMetadata->reflection->getAttributes(Audited::class);

        if ($attributes === []) {
            return;
        }

        $classMetadata->extras[Audited::class] = true;
    }
}
```
```php
$builder->addMetadataEnricher(new AuditMetadataEnricher());
```
:::note
Metadata enrichers also accept a priority. Since the metadata (including the
extras) can be [cached](caching.md), everything you store in `extras` must be
serializable.
:::

## Mapper factories

At the end of the chain the `Mapper` of the class turns the array into the
object and back. By default this is the `ReflectionMapper`, which sets and
reads the properties with reflection. A mapper factory can provide another
mapper for a class, for example generated code.

```php
use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Mapper\MapperFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\StackHydrator;

final class MoneyMapperFactory implements MapperFactory
{
    public function create(ClassMetadata $metadata, StackHydrator $hydrator): Mapper|null
    {
        if ($metadata->className !== Money::class) {
            return null;
        }

        return new MoneyMapper();
    }
}
```
```php
$builder->addMapperFactory(new MoneyMapperFactory());
```
The factories are asked once per class, the first mapper wins and is cached by
the hydrator together with its middlewares. Factories also accept a priority, a
factory with a higher priority is asked first. Every class without a mapper of
a factory is mapped with the `ReflectionMapper`.

### Nested objects

`StackHydrator::mapper()` returns the chain the hydrator runs for a class: its
mapper, wrapped in the middlewares and the lazy proxy which run for it. A class
with a class normalizer has no chain, then it returns `null`. A mapper can call
the chain of a nested class directly instead of going through the normalizer
and the hydrator again, the `ReflectionMapper` does this for properties with an
`ObjectNormalizer`.

Only do this if the context contains the hydrator which created the mapper:
a hydrator which wraps it has to see the nested objects.

```php
use Patchlevel\Hydrator\Hydrator;

if (($context[Hydrator::HYDRATOR] ?? null) === $this->hydrator) {
    $address = $this->hydrator->mapper(Address::class)?->hydrate($data['address'], $context);
}
```
:::warning
Ask for the mappers of nested classes once the mapper is used, not inside
`create()`. The hydrator caches the mapper only after `create()` returned, so
two classes which reference each other would create each other endlessly.
:::

## Writing your own extension

An extension implements the `Extension` interface and configures the builder.
This is the way to package a middleware together with its metadata enricher.

```php
use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\StackHydratorBuilder;

final class AuditExtension implements Extension
{
    public function configure(StackHydratorBuilder $builder): void
    {
        $builder->addMetadataEnricher(new AuditMetadataEnricher());
        $builder->addMiddleware(new AuditMiddleware());
    }
}
```
## Learn more

* [How to run code before extract and after hydrate](lifecycle-hooks.md)
* [How to encrypt sensitive data](cryptography.md)
* [How to reshape outdated stored data](upcasting.md)
* [How to speed up hydration with generated code](generated-code.md)
* [How to cache the metadata](caching.md)
