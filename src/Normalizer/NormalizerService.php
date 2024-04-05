<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

interface NormalizerService
{
    /** @throws InvalidArgument */
    public function normalize(mixed $value, NormalizerConfig $config): mixed;

    /** @throws InvalidArgument */
    public function denormalize(mixed $value, NormalizerConfig $config): mixed;
}
