<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class InferNormalizerWithNullableShapeItemsDto
{
    /** @param array{skill: ?Skill, status: Status} $shape */
    public function __construct(
        public array $shape,
    ) {
    }
}
