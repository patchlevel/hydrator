<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class NormalizerInBaseClassDefinedDto
{
    public function __construct(
        public StatusWithNormalizer $status,
        public ProfileCreatedWithNormalizer $profileCreated,
        public array $array,
    ) {
    }
}
