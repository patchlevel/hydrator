<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture;

use Patchlevel\Hydrator\Extension\Groups\Attribute\Groups;

final class AddressFixture
{
    public function __construct(
        #[Groups(['public', 'admin'])]
        public string $city,
        #[Groups('admin')]
        public string $street,
    ) {
    }
}
