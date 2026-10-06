<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

/**
 * Wraps the hydrator built by the {@see StackHydratorBuilder}, for example to add tracing around every call.
 */
interface HydratorDecorator
{
    /**
     * @param Hydrator      $hydrator the hydrator to wrap, either the stack hydrator or an already decorated one
     * @param StackHydrator $stack    the innermost stack hydrator, for decorators which need its metadata
     */
    public function decorate(Hydrator $hydrator, StackHydrator $stack): Hydrator;
}
