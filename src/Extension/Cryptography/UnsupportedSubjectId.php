<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

use function get_debug_type;
use function sprintf;

/** @experimental */
final class UnsupportedSubjectId extends RuntimeException implements HydratorException
{
    public function __construct(string $class, string $fieldName, mixed $subjectId)
    {
        parent::__construct(sprintf('Unsupported subject id for %s in field %s. Got %s.', $class, $fieldName, get_debug_type($subjectId)));
    }
}
