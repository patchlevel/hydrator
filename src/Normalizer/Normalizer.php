<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

interface Normalizer
{
    /** @throws InvalidArgument */
    public function normalize(mixed $value): mixed;

    /** @throws InvalidArgument */
    public function denormalize(mixed $value): mixed;
}
