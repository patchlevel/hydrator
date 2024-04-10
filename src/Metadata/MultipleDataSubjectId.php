<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use RuntimeException;

use function sprintf;

final class MultipleDataSubjectId extends RuntimeException implements MetadataException
{
    public function __construct(string $firstProperty, string $secondProperty)
    {
        parent::__construct(
            sprintf(
                'Multiple data subject id found: %s and %s.',
                $firstProperty,
                $secondProperty,
            ),
        );
    }
}
