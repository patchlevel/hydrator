<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use RuntimeException;

use function sprintf;

final class MissingDataSubjectId extends RuntimeException implements MetadataException
{
    /** @param class-string $class */
    public function __construct(string $class)
    {
        parent::__construct(
            sprintf('Personal data cannot used without a subject id. Please provide a subject id for %s.', $class),
        );
    }
}
