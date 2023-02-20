<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\NormalizedName;

final class DuplicateFieldNameDto
{
    public function __construct(
        #[NormalizedName('a')]
        public string $x,
        #[NormalizedName('a')]
        public string $y,
    ) {
    }
}
