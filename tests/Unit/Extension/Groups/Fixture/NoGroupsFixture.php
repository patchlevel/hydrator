<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture;

final class NoGroupsFixture
{
    public function __construct(
        public string $name = 'default',
        public int $age = 0,
    ) {
    }
}
