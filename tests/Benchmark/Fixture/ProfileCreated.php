<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark\Fixture;

use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\SensitiveData;

final class ProfileCreated
{
    /** @param list<Skill> $skills */
    public function __construct(
        #[ProfileIdNormalizer]
        #[DataSubjectId]
        public ProfileId $profileId,
        #[SensitiveData(fallback: 'unknown')]
        public string $name,
        public array $skills = [],
    ) {
    }
}
