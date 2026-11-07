<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Normalizer\ObjectMapNormalizer;

#[ObjectMapNormalizer([Circle::class => 'circle', Square::class => 'square'])]
abstract class Shape
{
}
