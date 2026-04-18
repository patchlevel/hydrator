<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark\Fixture;

use JMS\Serializer\Annotation\Type;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

final class ProfileCreated
{
    /** @param list<Skill> $skills */
    public function __construct(
        #[ProfileIdNormalizer]
        #[ProfileIdCaster]
        #[DataSubjectId]
        public ProfileId $profileId,
        #[SensitiveData(fallback: 'unknown')]
        public string $name,
        /**
         * @Type("list<Patchlevel\Hydrator\Tests\Benchmark\Fixture\Skill>")
         */
        public array $skills = [],
    ) {
    }
}
