<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

/**
 * The objects which are currently extracted within one call of the hydrator, to detect circular references. It is
 * passed along in the context, so every call has its own and nested objects share the one of their parent.
 *
 * @internal
 */
final class CallStack
{
    /** @var array<int, class-string> object id => class */
    public array $objects = [];

    /**
     * The call stack of the context, a new one is added to the context if there is none yet.
     *
     * @param array<string, mixed> $context
     */
    public static function of(array &$context): self
    {
        $callStack = $context[self::class] ?? null;

        if ($callStack instanceof self) {
            return $callStack;
        }

        return $context[self::class] = new self();
    }
}
