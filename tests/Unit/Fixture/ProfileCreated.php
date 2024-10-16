<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class ProfileCreated
{
    public function __construct(
        #[IdNormalizer]
        public ProfileId $profileId,
        #[EmailNormalizer]
        public Email $email,
    ) {
    }
}
