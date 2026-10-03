<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class AsymmetricVisibilityDto extends AsymmetricVisibilityParentDto
{
    public function __construct(
        public private(set) string $name,
        public protected(set) int $age,
        string $note,
    ) {
        parent::__construct($note);
    }
}
