# Tracing

The tracing extension measures every `hydrate` and `extract` call on the
hydrator. It wraps the hydrator in a [decorator](extensions.md#decorators) and
passes each call to a `Tracer`, which decides what to record: an event in the
Symfony profiler, a log entry or a span in your observability tool.

## Setup

Register the `TracingExtension` on the builder and pass it a `Tracer`. The
library ships with the `StopwatchTracer`, which records every call as an event
of the Symfony Stopwatch. It needs the `symfony/stopwatch` package.

```bash
composer require symfony/stopwatch
```
```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Tracing\StopwatchTracer;
use Patchlevel\Hydrator\Extension\Tracing\TracingExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Symfony\Component\Stopwatch\Stopwatch;

$stopwatch = new Stopwatch();

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new TracingExtension(new StopwatchTracer($stopwatch)))
    ->buildHydrator();
```
Each call shows up as an event named after the operation and the class, for
example `hydrate App\Profile\Event\ProfileCreated`, in the category
`hydrator`. You can pass another category as the second argument of the
`StopwatchTracer`.

:::warning
The decorators are only applied by `buildHydrator()`. The deprecated `build()`
throws a `DecoratorsNotApplied` exception as soon as an extension registers a
decorator, like the `TracingExtension` does.
:::

## Write your own tracer

A tracer implements the `Tracer` interface. It receives the operation, the
class and a callback which runs the actual call. The tracer has to call it,
return its result unchanged and let exceptions pass.

```php
use Patchlevel\Hydrator\Extension\Tracing\Operation;
use Patchlevel\Hydrator\Extension\Tracing\Tracer;
use Psr\Log\LoggerInterface;

final class SlowCallTracer implements Tracer
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly float $thresholdInMs = 5.0,
    ) {
    }

    public function trace(Operation $operation, string $class, Closure $callback): mixed
    {
        $start = hrtime(true);

        try {
            return $callback();
        } finally {
            $duration = (hrtime(true) - $start) / 1_000_000;

            if ($duration >= $this->thresholdInMs) {
                $this->logger->warning('Slow {operation} of {class} took {duration} ms', [
                    'operation' => $operation->value,
                    'class' => $class,
                    'duration' => $duration,
                ]);
            }
        }
    }
}
```
:::note
Only the calls on the hydrator itself are traced. Nested objects are hydrated
and extracted inside the call of their parent, so their time is part of the
parent's trace.
:::

## Learn more

* [How to write your own decorator](extensions.md#decorators)
* [How to create the hydrator](hydrator.md)
* [How to cache the metadata](caching.md)
