<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture;

use Patchlevel\Hydrator\Extension\Groups\Attribute\Groups;

final class UserFixture
{
    public function __construct(
        #[Groups(['public', 'admin'])]
        public string $id,
        #[Groups('public')]
        public string $name,
        #[Groups('admin')]
        public string $email,
        #[Groups(['public', 'admin'])]
        public AddressFixture $address,
        public string $internal = 'default',
    ) {
    }
}
