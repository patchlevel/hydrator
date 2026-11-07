<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Normalizer\ArrayShapeNormalizer;
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

final class ExplicitArrayShapeNormalizerDto
{
    /** @param array{status: Status}|null $shape */
    public function __construct(
        #[ArrayShapeNormalizer([
            'status' => new EnumNormalizer(),
            'skill' => new ObjectNormalizer(Skill::class),
        ])]
        public array|null $shape = null,
    ) {
    }
}
