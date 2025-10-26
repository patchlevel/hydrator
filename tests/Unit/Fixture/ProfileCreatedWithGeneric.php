<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class ProfileCreatedWithGeneric
{
    /** @param Wrapper<Email> $email */
    public function __construct(
        #[IdNormalizer]
        public ProfileId $profileId,
        public Wrapper $email,
    ) {
    }
}
