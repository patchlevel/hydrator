<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

final readonly class ChainTransformerFactory implements ClassTransformerFactory
{
    /** @param iterable<ClassTransformerFactory> $factories asked in this order, the first transformer wins */
    public function __construct(
        private iterable $factories,
    ) {
    }

    /**
     * @param ClassMetadata<T> $metadata
     *
     * @return ClassTransformer<T>|null
     *
     * @template T of object
     */
    public function create(ClassMetadata $metadata, TransformerResolver $resolver): ClassTransformer|null
    {
        foreach ($this->factories as $factory) {
            $transformer = $factory->create($metadata, $resolver);

            if ($transformer !== null) {
                return $transformer;
            }
        }

        return null;
    }
}
