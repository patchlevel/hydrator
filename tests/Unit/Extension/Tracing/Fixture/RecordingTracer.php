<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Tracing\Fixture;

use Closure;
use Patchlevel\Hydrator\Extension\Tracing\Operation;
use Patchlevel\Hydrator\Extension\Tracing\Tracer;

final class RecordingTracer implements Tracer
{
    /** @var list<string> */
    public array $traces = [];

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
        $this->traces[] = $operation->value . ' ' . $class;

        return $callback();
    }
}
