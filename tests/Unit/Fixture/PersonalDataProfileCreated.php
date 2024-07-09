<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Attribute\PersonalData;

final class PersonalDataProfileCreated
{
    public function __construct(
        #[IdNormalizer]
        #[NormalizedName('id')]
        #[DataSubjectId]
        public ProfileId $profileId,
        #[EmailNormalizer]
        #[PersonalData(fallback: new Email('unknown'))]
        public Email $email,
    ) {
    }
}
