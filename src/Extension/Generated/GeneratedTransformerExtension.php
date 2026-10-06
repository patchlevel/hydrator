<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\StackHydratorBuilder;

/**
 * Uses generated code instead of reflection for the classes which have a generated transformer in the cache path.
 * Generate them ahead of time with the {@see TransformerCompiler}, or let them be generated on first use.
 */
final class GeneratedTransformerExtension implements Extension
{
    /**
     * @param string $cachePath    Directory where the generated transformers are stored.
     * @param bool   $autoGenerate Generate missing transformers on first use, otherwise classes without generated
     *                             code are transformed with reflection.
     */
    public function __construct(
        private readonly string $cachePath,
        private readonly bool $autoGenerate = false,
    ) {
    }

    public function configure(StackHydratorBuilder $builder): void
    {
        $builder->addTransformerFactory(new GeneratedTransformerFactory(
            new TransformerFiles($this->cachePath),
            $this->autoGenerate,
        ));
    }
}
