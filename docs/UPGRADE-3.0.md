---
searchable: false
---
# Upgrade 3.0

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
