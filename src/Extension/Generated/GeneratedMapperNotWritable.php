<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

use function sprintf;

final class GeneratedMapperNotWritable extends RuntimeException implements HydratorException
{
    public function __construct(string $file)
    {
        parent::__construct(sprintf('The generated mapper could not be written to "%s".', $file));
    }
}
