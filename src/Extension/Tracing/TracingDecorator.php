<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Tracing;

use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\HydratorDecorator;
use Patchlevel\Hydrator\StackHydrator;

final class TracingDecorator implements HydratorDecorator
{
    public function __construct(
        private readonly Tracer $tracer,
    ) {
    }

    public function decorate(Hydrator $hydrator, StackHydrator $stack): Hydrator
    {
        return new TracingHydrator($hydrator, $this->tracer);
    }
}
