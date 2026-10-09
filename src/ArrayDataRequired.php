<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use RuntimeException;

use function sprintf;

final class ArrayDataRequired extends RuntimeException implements HydratorException
{
    /** @param class-string $class */
    public function __construct(string $class)
    {
        parent::__construct(sprintf(
            'The data for the class "%s" must be an array. If you want to use another data type, you need to add a normalizer to the class.',
            $class,
        ));
    }
}
