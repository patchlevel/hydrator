<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

final class NestedObjectsDto
{
    /** @param list<Skill|null>|null $skills */
    public function __construct(
        public string $name,
        #[ObjectNormalizer(Skill::class)]
        public Skill|null $skill = null,
        #[ArrayNormalizer(new ObjectNormalizer(Skill::class))]
        public array|null $skills = null,
        #[ObjectNormalizer(ChildDto::class)]
        public ChildDto|null $child = null,
    ) {
    }
}
