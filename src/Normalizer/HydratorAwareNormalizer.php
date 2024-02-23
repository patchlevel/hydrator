<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Patchlevel\Hydrator\Hydrator;

interface HydratorAwareNormalizer
{
    public function setHydrator(Hydrator $hydrator): void;
}
