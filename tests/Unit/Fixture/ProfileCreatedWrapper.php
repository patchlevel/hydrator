<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

final class ProfileCreatedWrapper
{
    public function __construct(
        #[ObjectNormalizer(ProfileCreated::class)]
        #[NormalizedName('event')]
        public ProfileCreated $profileCreated,
    ) {
    }
}
