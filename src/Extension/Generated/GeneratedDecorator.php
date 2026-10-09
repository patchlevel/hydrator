<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Closure;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\HydratorDecorator;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Middleware\HydratorAwareMiddleware;
use Patchlevel\Hydrator\StackHydrator;

/** @internal */
final class GeneratedDecorator implements HydratorDecorator
{
    /** @param Closure(MetadataFactory): GeneratedMiddleware $load generates or loads the middleware */
    public function __construct(
        private readonly GeneratedMiddlewareSlot $slot,
        private readonly Closure $load,
    ) {
    }

    public function decorate(Hydrator $hydrator, StackHydrator $stack): Hydrator
    {
        $middleware = ($this->load)(new StackMetadataFactory($stack));

        $this->slot->load($middleware);

        if ($middleware instanceof HydratorAwareMiddleware) {
            $middleware->setHydrator($stack);
        }

        return new GeneratedHydrator($hydrator, $stack, $middleware);
    }
}
