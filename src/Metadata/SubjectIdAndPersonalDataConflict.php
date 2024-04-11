<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use RuntimeException;

use function sprintf;

final class SubjectIdAndPersonalDataConflict extends RuntimeException implements MetadataException
{
    /** @param class-string $class */
    public function __construct(string $class, string $property)
    {
        parent::__construct(
            sprintf(
                'Personal data cannot be used as a subject id. Fix subject id for %s::%s.',
                $class,
                $property,
            ),
        );
    }
}
