<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

final class NoMoreMiddleware extends RuntimeException implements HydratorException
{
    public function __construct()
    {
        parent::__construct('no more middlewares');
    }
}
