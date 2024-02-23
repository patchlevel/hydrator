<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use RuntimeException;

use function implode;
use function sprintf;

final class CircularReference extends RuntimeException implements HydratorException
{
    /** @param list<class-string> $classes */
    public function __construct(array $classes)
    {
        parent::__construct(sprintf('Circular reference detected: %s', implode(' -> ', $classes)));
    }
}
