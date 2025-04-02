<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark\Fixture;

use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\PersonalData;

final class ProfileCreated
{
    public function __construct(
        #[ProfileIdNormalizer]
        #[DataSubjectId]
        public ProfileId $profileId,
        #[PersonalData(fallback: 'unknown')]
        public string $name,
        /** @var list<Skill> */
        public array $skills = [],
    ) {
    }
}
