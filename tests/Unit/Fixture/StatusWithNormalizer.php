<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Normalizer\EnumNormalizer;

#[EnumNormalizer]
enum StatusWithNormalizer: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Closed = 'closed';
}
