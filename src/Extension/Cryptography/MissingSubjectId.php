<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

use function sprintf;

/** @experimental */
final class MissingSubjectId extends RuntimeException implements HydratorException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('Missing subject id %s.', $name));
    }
}
