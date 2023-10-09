<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark\Fixture;

final class NameChanged
{
    public function __construct(
        public string $name,
    ) {
    }
}
