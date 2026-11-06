<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use DateTimeImmutable;

final class InferNormalizerWithArrayShapeDto
{
    /** @param array{height: int, nickname: string|null, skills: list<Skill>, status: Status|null, createdAt: DateTimeImmutable} $profile */
    public function __construct(
        public array $profile,
    ) {
    }
}
