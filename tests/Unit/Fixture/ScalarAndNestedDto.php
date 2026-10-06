<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class ScalarAndNestedDto
{
    public function __construct(
        public string $name,
        public LifecycleFixture $child,
    ) {
    }
}
