<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Tracing;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\StackHydratorBuilder;

final class TracingExtension implements Extension
{
    public function __construct(
        private readonly Tracer $tracer,
    ) {
    }

    public function configure(StackHydratorBuilder $builder): void
    {
        $builder->addDecorator(new TracingDecorator($this->tracer), 32);
    }
}
