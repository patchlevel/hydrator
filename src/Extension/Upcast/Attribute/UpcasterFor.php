<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Upcast\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class UpcasterFor
{
    /** @param class-string $className */
    public function __construct(
        public readonly string $className,
    ) {
    }
}
