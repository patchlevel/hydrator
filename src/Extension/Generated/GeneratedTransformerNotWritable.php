<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

use function sprintf;

final class GeneratedTransformerNotWritable extends RuntimeException implements HydratorException
{
    public function __construct(string $file)
    {
        parent::__construct(sprintf('The generated transformer could not be written to "%s".', $file));
    }
}
