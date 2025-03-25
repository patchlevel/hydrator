<?php

namespace Patchlevel\Hydrator\Event;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

final class PreHydrate
{
    public function __construct(
        public readonly ClassMetadata $metadata,
        public array $data,
    ) {
    }
}