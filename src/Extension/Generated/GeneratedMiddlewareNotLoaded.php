<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

final class GeneratedMiddlewareNotLoaded extends RuntimeException implements HydratorException
{
    public function __construct()
    {
        parent::__construct('The generated middleware is not loaded, build the hydrator with StackHydratorBuilder::buildHydrator().');
    }
}
