<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\Ignore;

final class IgnoreDto
{
    public function __construct(
        #[ProfileIdNormalizer]
        public ProfileId $profileId,
        #[EmailNormalizer]
        #[Ignore]
        public Email $email,
    ) {
    }
}
