<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

enum StatusWithInterfaceNormalizer: string implements HasEnumNormalizer
{
    case Draft = 'draft';
    case Published = 'published';
}
