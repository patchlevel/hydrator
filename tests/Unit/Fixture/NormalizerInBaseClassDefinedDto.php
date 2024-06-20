<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class NormalizerInBaseClassDefinedDto
{
    /** @param array<string> $array */
    public function __construct(
        public StatusWithNormalizer $status,
        public ProfileCreatedWithNormalizer $profileCreated,
        public array $array,
    ) {
    }
}
