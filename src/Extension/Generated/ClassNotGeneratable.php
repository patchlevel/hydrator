<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use InvalidArgumentException;
use Patchlevel\Hydrator\HydratorException;

use function sprintf;

final class ClassNotGeneratable extends InvalidArgumentException implements HydratorException
{
    public function __construct(string $class, string $reason)
    {
        parent::__construct(sprintf('A middleware for class "%s" can not be generated: %s', $class, $reason));
    }
}
