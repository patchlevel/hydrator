<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography;

use Patchlevel\Hydrator\Metadata\MetadataException;
use RuntimeException;

use function sprintf;

/** @experimental */
final class DuplicateSubjectIdIdentifier extends RuntimeException implements MetadataException
{
    /** @param class-string $class */
    public function __construct(string $class, string $firstProperty, string $secondProperty, string $subjectIdIdentifier)
    {
        parent::__construct(
            sprintf(
                'Duplicate subject id identifier found. Used %s for %s::%s and %s::%s.',
                $subjectIdIdentifier,
                $class,
                $firstProperty,
                $class,
                $secondProperty,
            ),
        );
    }
}
