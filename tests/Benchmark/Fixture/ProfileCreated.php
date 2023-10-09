<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark\Fixture;

final class ProfileCreated
{
    public function __construct(
        #[ProfileIdNormalizer]
        public ProfileId $profileId,
        public string $name,
    ) {
    }
}
