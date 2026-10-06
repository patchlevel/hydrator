# Extensions

The `StackHydrator` is assembled from small building blocks: middlewares that
wrap the hydration process, [guessers](guesser.md) that resolve normalizers,
metadata enrichers that add information to the class metadata and transformer
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
| `GeneratedTransformerExtension` | [Generated code](generated-code.md), use generated mapping code instead of reflection. |

## Middleware

A middleware wraps the hydration and extraction process, similar to HTTP
middlewares. It can modify the incoming data, the outgoing array or the object
itself, and passes the call on to the rest of the stack with `$next`. After the last
middleware the [transformer](#transformer-factories) does the actual property
mapping.

```php
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Next;

final class RemoveNullValuesMiddleware implements Middleware
{
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Next $next): object
    {
        return $next->hydrate($metadata, $data, $context);
    }

    public function extract(ClassMetadata $metadata, object $object, array $context, Next $next): array
    {
        $data = $next->extract($metadata, $object, $context);

        return array_filter($data, static fn (mixed $value) => $value !== null);
    }
}
```
Middlewares are added with a priority, higher priorities run first (outermost).
The transformation always runs last, after all middlewares.

```php
$builder->addMiddleware(new RemoveNullValuesMiddleware(), 0);
```
## Skipping middlewares

A middleware usually only has something to do for a few classes. Instead of
walking through the whole stack every time, a middleware can implement
`SkippableMiddleware` and tell the hydrator that it is not needed for a class.
The decision is made once per class and then reused, so it must only depend on
the metadata.

The `skip` method returns a `Skip` case: `Skip::None` to always run,
`Skip::Hydrate` or `Skip::Extract` to be left out in one direction only and
`Skip::Both` to be left out completely.

```php
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Next;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;

final class RemoveNullValuesMiddleware implements SkippableMiddleware
{
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Next $next): object
    {
        return $next->hydrate($metadata, $data, $context);
    }

    public function extract(ClassMetadata $metadata, object $object, array $context, Next $next): array
    {
        $data = $next->extract($metadata, $object, $context);

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
If every middleware skips a class, the hydrator calls the transformer directly
without building the middleware stack.
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

## Transformer factories

At the end of the stack a `ClassTransformer` turns the array into the object
and back. By default this is the `ReflectionTransformer`, which sets and reads
the properties with reflection. A transformer factory can provide another
transformer for a class, for example generated code.

```php
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use Patchlevel\Hydrator\Transformer\ClassTransformerFactory;
use Patchlevel\Hydrator\Transformer\TransformerResolver;

final class MoneyTransformerFactory implements ClassTransformerFactory
{
    public function create(ClassMetadata $metadata, TransformerResolver $resolver): ClassTransformer|null
    {
        if ($metadata->className !== Money::class) {
            return null;
        }

        return new MoneyTransformer();
    }
}
```
```php
$builder->addTransformerFactory(new MoneyTransformerFactory());
```
The factories are asked once per class, the first transformer wins and is
cached by the hydrator. Factories also accept a priority, a factory with a
higher priority is asked first. The `ReflectionTransformerFactory` is always
asked last, so every class without its own transformer is transformed with
reflection.

The `TransformerResolver` gives the transformer insight into the hydrator which
created it. With `direct()` it asks whether the hydrator would call the
transformer of a nested class directly, without a middleware, a class
normalizer or a lazy proxy. Only then a transformer may map nested objects in
place, like the [generated transformers](generated-code.md) do.
:::warning
Use the resolver once the transformer is used, not inside `create()`. The
hydrator caches the transformer only after `create()` returned.
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
