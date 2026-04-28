<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use RuntimeException;

/** @experimental */
final class MissingMiddlewares extends RuntimeException implements HydratorException
{
    public function __construct()
    {
        parent::__construct('Missing middlewares.');
    }
}
