<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

final class MissingHydrator extends RuntimeException implements HydratorException
{
    public function __construct()
    {
        parent::__construct('no hydrator set');
    }
}
