<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class Square extends Shape
{
    public function __construct(
        public int $size,
    ) {
    }
}
