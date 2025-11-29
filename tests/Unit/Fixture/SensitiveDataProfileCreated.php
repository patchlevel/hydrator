<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Attribute\SensitiveData;

final class SensitiveDataProfileCreated
{
    public function __construct(
        #[IdNormalizer]
        #[NormalizedName('id')]
        #[DataSubjectId]
        public ProfileId $profileId,
        #[EmailNormalizer]
        #[SensitiveData(fallback: new Email('unknown'))]
        public Email $email,
    ) {
    }
}
