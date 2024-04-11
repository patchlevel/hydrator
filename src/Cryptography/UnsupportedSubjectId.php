<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use RuntimeException;

use function get_debug_type;
use function sprintf;

final class UnsupportedSubjectId extends RuntimeException
{
    public function __construct(string $class, string $fieldName, mixed $subjectId)
    {
        parent::__construct(sprintf('Unsupported subject id for %s in field %s. Got %s.', $class, $fieldName, get_debug_type($subjectId)));
    }
}
