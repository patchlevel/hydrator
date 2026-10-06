<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

/**
 * The objects which are currently extracted, shared by the transformers of a factory to detect circular references
 * across classes.
 *
 * @internal
 */
final class CallStack
{
    /** @var array<int, class-string> object id => class */
    public array $objects = [];
}
