<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Normalizer\EnumNormalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

final class AutoTypeDto
{
    public function __construct(
        #[EnumNormalizer]
        public Status $status,
        #[ObjectNormalizer]
        public ProfileCreated $profileCreated,
    ) {
    }
}
