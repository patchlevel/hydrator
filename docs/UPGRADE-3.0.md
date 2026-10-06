---
searchable: false
---
# Upgrade 3.0

## Normalizer

### HydratorAwareNormalizer

`HydratorAwareNormalizer` has been removed. The hydrator is no longer injected
into the normalizers of the metadata, it is passed with every call in the
context under the key `Hydrator::HYDRATOR`. The `StackHydrator` adds itself to
the context, unless another hydrator which wraps it is already there. Remove
`setHydrator()` from your normalizers and read the hydrator from the context.

before:

```php
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Normalizer\HydratorAwareNormalizer;
use Patchlevel\Hydrator\Normalizer\MissingHydrator;
use Patchlevel\Hydrator\Normalizer\Normalizer;

final class ProfileNormalizer implements Normalizer, HydratorAwareNormalizer
{
    private Hydrator|null $hydrator = null;

    public function setHydrator(Hydrator $hydrator): void
    {
        $this->hydrator = $hydrator;
    }

    public function normalize(mixed $value, array $context): mixed
    {
        if (!$this->hydrator) {
            throw new MissingHydrator();
        }

        return $this->hydrator->extract($value, $context);
    }

    public function denormalize(mixed $value, array $context): mixed
    {
        if (!$this->hydrator) {
            throw new MissingHydrator();
        }

        return $this->hydrator->hydrate(Profile::class, $value, $context);
    }
}
```
after:

```php
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Normalizer\MissingHydrator;
use Patchlevel\Hydrator\Normalizer\Normalizer;

final class ProfileNormalizer implements Normalizer
{
    public function normalize(mixed $value, array $context): mixed
    {
        return $this->hydrator($context)->extract($value, $context);
    }

    public function denormalize(mixed $value, array $context): mixed
    {
        return $this->hydrator($context)->hydrate(Profile::class, $value, $context);
    }

    private function hydrator(array $context): Hydrator
    {
        $hydrator = $context[Hydrator::HYDRATOR] ?? null;

        if (!$hydrator instanceof Hydrator) {
            throw new MissingHydrator();
        }

        return $hydrator;
    }
}
```
:::warning
Pass the context on when you call the hydrator from a normalizer, otherwise
nested objects lose the hydrator and all other context values.
:::

### Built-in normalizers

`ObjectNormalizer`, `ObjectMapNormalizer`, `ArrayNormalizer` and
`ArrayShapeNormalizer` no longer have a `setHydrator()` method. If you use them
outside of a hydrator, for example in a test, pass the hydrator in the context
instead.

before:

```php
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

$normalizer = new ObjectNormalizer(Profile::class);
$normalizer->setHydrator($hydrator);

$data = $normalizer->normalize($profile, []);
```
after:

```php
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

$normalizer = new ObjectNormalizer(Profile::class);

$data = $normalizer->normalize($profile, [Hydrator::HYDRATOR => $hydrator]);
```
`ObjectNormalizer` and `ObjectMapNormalizer` throw a `MissingHydrator`
exception only if they actually need the hydrator. A `null` value is now
normalized and denormalized without one.

## Hydrator

### Context

The context which middlewares, normalizers, upcasters and lifecycle hooks
receive now contains the hydrator under the key `Hydrator::HYDRATOR`. Don't use
the key `hydrator` for your own context values. If you serialize, log or compare
the context, leave this key out.

### Wrapping a hydrator

If you wrap the hydrator in your own `Hydrator` implementation, add it to the
context before you call the inner hydrator. Nested objects then also go
through your wrapper.

```php
use Patchlevel\Hydrator\Hydrator;

final class LoggingHydrator implements Hydrator
{
    public function __construct(
        private readonly Hydrator $hydrator,
    ) {
    }

    public function hydrate(string $class, mixed $data, array $context = []): object
    {
        $context[Hydrator::HYDRATOR] ??= $this;

        return $this->hydrator->hydrate($class, $data, $context);
    }

    public function extract(object $object, array $context = []): mixed
    {
        $context[Hydrator::HYDRATOR] ??= $this;

        return $this->hydrator->extract($object, $context);
    }
}
```
## Middleware

### Next instead of Stack

`Patchlevel\Hydrator\Middleware\Stack` has been removed in favor of
`Patchlevel\Hydrator\Middleware\Next`. A middleware no longer asks the stack for
the next middleware and calls it with the stack again, it calls `$next`
directly with the metadata, the data and the context. Change the last parameter
of `hydrate()` and `extract()` in your middlewares to `Next $next`.

before:

```php
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;

final class AuditMiddleware implements Middleware
{
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Stack $stack): object
    {
        return $stack->next()->hydrate($metadata, $data, $context, $stack);
    }

    public function extract(ClassMetadata $metadata, object $object, array $context, Stack $stack): array
    {
        return $stack->next()->extract($metadata, $object, $context, $stack);
    }
}
```
after:

```php
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Next;

final class AuditMiddleware implements Middleware
{
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Next $next): object
    {
        return $next->hydrate($metadata, $data, $context);
    }

    public function extract(ClassMetadata $metadata, object $object, array $context, Next $next): array
    {
        return $next->extract($metadata, $object, $context);
    }
}
```
In tests, where a middleware is called on its own, pass a `Next` with the
following middlewares instead of a `Stack`.

before:

```php
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Middleware\TransformMiddleware;

$object = $middleware->hydrate($metadata, $data, [], new Stack([new TransformMiddleware()]));
```
after:

```php
use Patchlevel\Hydrator\Middleware\Next;
use Patchlevel\Hydrator\Middleware\TransformMiddleware;

$object = $middleware->hydrate($metadata, $data, [], new Next([new TransformMiddleware()]));
```
## Caching

The serialized form of the normalizers changed. Clear the
[metadata cache](caching.md) when you deploy the new version.
