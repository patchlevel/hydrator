<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

final class ExplicitObjectNormalizerDto
{
    public function __construct(
        #[ObjectNormalizer(ProfileId::class)]
        public ProfileId $profileId,
    ) {
    }
}
