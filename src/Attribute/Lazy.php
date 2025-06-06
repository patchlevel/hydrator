<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Lazy
{
    public function __construct(
        public readonly bool $enabled = true,
    ) {
    }
}
