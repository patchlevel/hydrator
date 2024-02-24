<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

final class Circle1Dto
{
    #[ObjectNormalizer(Circle2Dto::class)]
    public object|null $to = null;
}
