<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use Patchlevel\Hydrator\Metadata\MetadataException;
use RuntimeException;

use function sprintf;

final class SubjectIdAndSensitiveDataConflict extends RuntimeException implements MetadataException
{
    /** @param class-string $class */
    public function __construct(string $class, string $property)
    {
        parent::__construct(
            sprintf(
                'Sensitive data cannot be used as a subject id. Fix subject id for %s::%s.',
                $class,
                $property,
            ),
        );
    }
}
