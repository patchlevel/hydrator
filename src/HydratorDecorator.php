<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

/**
 * Wraps the hydrator built by the {@see StackHydratorBuilder}, for example to add tracing around every call.
 */
interface HydratorDecorator
{
    /** @param Hydrator $hydrator the hydrator to wrap, either the stack hydrator or an already decorated one */
    public function decorate(Hydrator $hydrator): Hydrator;
}
