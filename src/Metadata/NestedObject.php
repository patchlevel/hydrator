<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

use function class_exists;

/**
 * A property which holds an object, or a list of objects, of another class. Transformers can map these objects with
 * the handler of the nested class directly, instead of passing them through the normalizer and the hydrator.
 */
final class NestedObject
{
    /** @param class-string $className */
    public function __construct(
        public readonly string $className,
        public readonly bool $list,
    ) {
    }

    /** The nested object of a property with an object normalizer, directly or in an array normalizer. */
    public static function of(Normalizer $normalizer): self|null
    {
        $list = false;

        if ($normalizer instanceof ArrayNormalizer) {
            $list = true;
            $normalizer = $normalizer->innerNormalizer();
        }

        if (!$normalizer instanceof ObjectNormalizer) {
            return null;
        }

        try {
            $className = $normalizer->className();
        } catch (InvalidType) {
            return null;
        }

        if (!class_exists($className)) {
            return null;
        }

        return new self($className, $list);
    }
}
