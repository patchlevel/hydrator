<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class Circle extends Shape
{
    public function __construct(
        public int $radius,
    ) {
    }
}
