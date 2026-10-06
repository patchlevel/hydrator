<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Tracing;

use Closure;
use Symfony\Component\Stopwatch\Stopwatch;

use function sprintf;

/** Records every call as an event of the Symfony Stopwatch, which shows up in the Symfony profiler. */
final class StopwatchTracer implements Tracer
{
    public function __construct(
        private readonly Stopwatch $stopwatch,
        private readonly string $category = 'hydrator',
    ) {
    }

    /**
     * @param class-string $class
     * @param Closure(): T $callback
     *
     * @return T
     *
     * @template T
     */
    public function trace(Operation $operation, string $class, Closure $callback): mixed
    {
        $event = $this->stopwatch->start(sprintf('%s %s', $operation->value, $class), $this->category);

        try {
            return $callback();
        } finally {
            $event->stop();
        }
    }
}
