<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

use function sprintf;

final class OutdatedGeneratedMiddleware extends RuntimeException implements HydratorException
{
    public function __construct(string $class)
    {
        parent::__construct(sprintf('The generated middleware does not match the current metadata of class "%s". Regenerate the middleware.', $class));
    }
}
