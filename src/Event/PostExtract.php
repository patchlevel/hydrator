<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Event;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

final class PostExtract
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    public function __construct(
        public array $data,
        public readonly ClassMetadata $metadata,
        public array $context = [],
    ) {
    }
}
