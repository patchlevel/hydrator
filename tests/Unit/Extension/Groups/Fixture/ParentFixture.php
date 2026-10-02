<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture;

use Patchlevel\Hydrator\Extension\Groups\Attribute\Groups;

final class ParentFixture
{
    public function __construct(
        #[Groups(['parent', 'child'])]
        public string $name,
        #[Groups('parent')]
        public ChildFixture|null $child = null,
    ) {
    }
}
