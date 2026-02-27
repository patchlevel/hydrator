<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

interface NormalizerWithContext extends Normalizer
{
    /**
     * @param array<string, mixed> $context
     *
     * @throws InvalidArgument
     */
    public function normalize(mixed $value, array $context = []): mixed;

    /**
     * @param array<string, mixed> $context
     *
     * @throws InvalidArgument
     */
    public function denormalize(mixed $value, array $context = []): mixed;
}
