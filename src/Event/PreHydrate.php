<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Event;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

final class PreHydrate
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public array $data,
        public readonly ClassMetadata $metadata,
    ) {
    }
}
