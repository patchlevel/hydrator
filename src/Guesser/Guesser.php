<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Guesser;

use Patchlevel\Hydrator\Normalizer\Normalizer;

interface Guesser
{
    /** @param class-string $className */
    public function guess(string $className): Normalizer|null;
}
