<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Mapper;

/**
 * The objects which are currently extracted, shared by the reflection mappers of a hydrator to detect circular
 * references across classes.
 *
 * @internal
 */
final class CallStack
{
    /** @var array<int, class-string> object id => class */
    public array $objects = [];
}
