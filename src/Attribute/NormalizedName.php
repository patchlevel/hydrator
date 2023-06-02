<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class NormalizedName
{
    public function __construct(
        private readonly string $name,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }
}
