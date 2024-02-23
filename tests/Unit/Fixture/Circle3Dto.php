<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

final class Circle3Dto
{
    #[ObjectNormalizer(Circle1Dto::class)]
    public object|null $to = null;
}
