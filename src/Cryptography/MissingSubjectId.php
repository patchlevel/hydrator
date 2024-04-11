<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use RuntimeException;

use function sprintf;

final class MissingSubjectId extends RuntimeException
{
    /** @param class-string $class */
    public function __construct(string $class, string $fieldName)
    {
        parent::__construct(sprintf('Missing subject id for %s in field %s.', $class, $fieldName));
    }
}
