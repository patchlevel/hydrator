<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class Context
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public readonly array $context,
    ) {
    }
}
