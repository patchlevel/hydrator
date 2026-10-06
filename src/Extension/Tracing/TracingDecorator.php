<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Tracing;

use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\HydratorDecorator;

final class TracingDecorator implements HydratorDecorator
{
    public function __construct(
        private readonly Tracer $tracer,
    ) {
    }

    public function decorate(Hydrator $hydrator): Hydrator
    {
        return new TracingHydrator($hydrator, $this->tracer);
    }
}
