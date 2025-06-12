<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\Lazy;

#[Lazy]
final class LazyProfileCreated
{
    public function __construct(
        #[IdNormalizer]
        public ProfileId $profileId,
        #[EmailNormalizer]
        public Email $email,
    ) {
    }
}
