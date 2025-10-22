<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Event;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

final class PreExtract
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public object $object,
        public readonly ClassMetadata $metadata,
        public array $context = [],
    ) {
    }
}
