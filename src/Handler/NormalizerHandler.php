<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Handler;

use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\ObjectRequired;

/**
 * The class has a class normalizer, which is called instead of the middlewares and the transformer.
 *
 * @internal
 */
final class NormalizerHandler implements HydrateHandler, ExtractHandler
{
    /** @param class-string $class */
    public function __construct(
        private readonly string $class,
        private readonly Normalizer $normalizer,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function hydrate(mixed $data, array $context): object
    {
        $object = $this->normalizer->denormalize($data, $context);

        if (!$object instanceof $this->class) {
            throw new ObjectRequired($this->class, $this->normalizer::class);
        }

        return $object;
    }

    /** @param array<string, mixed> $context */
    public function extract(object $object, array $context): mixed
    {
        return $this->normalizer->normalize($object, $context);
    }
}
