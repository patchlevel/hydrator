<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Handler;

use Patchlevel\Hydrator\HydratorException;

/**
 * What the hydrator does for a class while hydrating, decided once per class: call the class normalizer, run the
 * middlewares or create a lazy proxy. If only the transformer has to run, the hydrator uses the transformer itself.
 * Transformers get the handlers of nested classes from the {@see \Patchlevel\Hydrator\Transformer\TransformerResolver}.
 */
interface HydrateHandler
{
    /**
     * @param array<string, mixed> $context
     *
     * @throws HydratorException
     */
    public function hydrate(mixed $data, array $context): object;
}
