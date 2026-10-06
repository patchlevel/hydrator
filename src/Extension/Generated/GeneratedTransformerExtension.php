<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\StackHydratorBuilder;

/**
 * Uses generated code instead of reflection for the classes which have a generated transformer in the cache path.
 * The code is never generated at runtime, write it beforehand with the {@see GeneratedTransformerWarmer}.
 */
final class GeneratedTransformerExtension implements Extension
{
    /** @param string $cachePath Directory where the warmer stored the generated transformers. */
    public function __construct(
        private readonly string $cachePath,
    ) {
    }

    public function configure(StackHydratorBuilder $builder): void
    {
        $builder->addTransformerFactory(new GeneratedTransformerFactory(new TransformerFiles($this->cachePath)));
    }
}
