# Extensions

The `StackHydrator` is assembled from small building blocks: middlewares that
wrap the hydration process, [guessers](guesser.md) that resolve normalizers and
metadata enrichers that add information to the class metadata. An extension
bundles such building blocks so they can be registered with a single call.

## Using extensions

Extensions are registered on the `StackHydratorBuilder` with `useExtension`.
The `CoreExtension` provides the default behaviour and should (almost) always
be there.

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Lifecycle\LifecycleExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new LifecycleExtension())
    ->build();
```
## Built-in extensions

The library ships with five extensions out of the box:

| Extension | Purpose |
| --- | --- |
| `CoreExtension` | The default behaviour, the `TransformMiddleware` and the `BuiltInGuesser`. |
| `LifecycleExtension` | [Lifecycle hooks](lifecycle-hooks.md), run code before and after the extract and hydrate process. |
| `CryptographyExtension` | [Cryptography](cryptography.md), encrypt and decrypt sensitive data with crypto-shredding. |
| `UpcastExtension` | [Upcasting](upcasting.md), reshape outdated stored data while it is hydrated. |
| `GeneratedMiddlewareExtension` | [Generated code](generated-code.md), generated mapping code for a fixed set of classes on top of the `CoreExtension`. |

## Middleware

A middleware wraps the hydration and extraction process, similar to HTTP
middlewares. It can modify the incoming data, the outgoing array or the object
itself, and then delegates to the next middleware on the stack. The innermost
middleware is the `TransformMiddleware`, which does the actual property mapping.

```php
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;

final class RemoveNullValuesMiddleware implements Middleware
{
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Stack $stack): object
    {
        return $stack->next()->hydrate($metadata, $data, $context, $stack);
    }

    public function extract(ClassMetadata $metadata, object $object, array $context, Stack $stack): array
    {
        $data = $stack->next()->extract($metadata, $object, $context, $stack);

        return array_filter($data, static fn (mixed $value) => $value !== null);
    }
}
```
Middlewares are added with a priority, higher priorities run first (outermost).
The `TransformMiddleware` from the `CoreExtension` has priority `-64`, so it
always runs last.

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
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;
use Patchlevel\Hydrator\Middleware\Stack;

final class RemoveNullValuesMiddleware implements SkippableMiddleware
{
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Stack $stack): object
    {
        return $stack->next()->hydrate($metadata, $data, $context, $stack);
    }

    public function extract(ClassMetadata $metadata, object $object, array $context, Stack $stack): array
    {
        $data = $stack->next()->extract($metadata, $object, $context, $stack);

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

:::warning
At least one middleware has to run. If every middleware skips a class, an
`AllMiddlewaresSkipped` exception is thrown.
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
