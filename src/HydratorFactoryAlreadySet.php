<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use RuntimeException;

final class HydratorFactoryAlreadySet extends RuntimeException implements HydratorException
{
    public function __construct()
    {
        parent::__construct('A hydrator factory is already set, only one extension can replace the hydrator.');
    }
}
