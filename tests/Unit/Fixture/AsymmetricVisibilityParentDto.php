<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

abstract class AsymmetricVisibilityParentDto
{
    public function __construct(
        public private(set) string $note,
    ) {
    }
}
