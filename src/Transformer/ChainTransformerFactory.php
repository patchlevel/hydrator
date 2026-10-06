<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\StackHydrator;

final readonly class ChainTransformerFactory implements ClassTransformerFactory
{
    /** @param iterable<ClassTransformerFactory> $factories asked in this order, the first transformer wins */
    public function __construct(
        private iterable $factories,
    ) {
    }

    public function create(ClassMetadata $metadata, StackHydrator $hydrator): ClassTransformer|null
    {
        foreach ($this->factories as $factory) {
            $transformer = $factory->create($metadata, $hydrator);

            if ($transformer !== null) {
                return $transformer;
            }
        }

        return null;
    }
}
