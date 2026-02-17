<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class NestedLifecycleDto
{
    /** @param list<LifecycleFixture> $items */
    public function __construct(
        public LifecycleFixture $child,
        public array $items = [],
    ) {
    }
}
