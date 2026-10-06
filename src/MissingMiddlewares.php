<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use RuntimeException;

/** @deprecated a hydrator without middlewares is valid, it only transforms the data */
final class MissingMiddlewares extends RuntimeException implements HydratorException
{
    public function __construct()
    {
        parent::__construct('Missing middlewares.');
    }
}
