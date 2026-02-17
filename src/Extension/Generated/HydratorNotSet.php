<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

use function sprintf;

final class HydratorNotSet extends RuntimeException implements HydratorException
{
    public function __construct(string $middleware)
    {
        parent::__construct(sprintf('The generated middleware "%s" has no hydrator. It must be used inside a StackHydrator.', $middleware));
    }
}
