<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Attribute;

use Attribute;

/** @experimental */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class DataSubjectId
{
    public function __construct(
        public readonly string $name = 'default',
    ) {
    }
}
