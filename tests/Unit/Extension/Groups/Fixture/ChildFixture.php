<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture;

use Patchlevel\Hydrator\Extension\Groups\Attribute\Groups;

final class ChildFixture
{
    public function __construct(
        #[Groups(['parent', 'child'])]
        public string $name,
        #[Groups('child')]
        public ParentFixture|null $parent = null,
    ) {
    }
}
