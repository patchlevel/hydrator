<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

use function sprintf;

final class MissingHydrator extends RuntimeException implements HydratorException
{
    public function __construct()
    {
        parent::__construct(sprintf('no hydrator in the context, it is expected under the key "%s"', Hydrator::HYDRATOR));
    }
}
