<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Fixture;

use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\EmailNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\IdNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;

final class RenamedSensitiveDataProfileCreated
{
    public function __construct(
        #[IdNormalizer]
        #[NormalizedName('id', aliases: ['profileId'])]
        #[DataSubjectId]
        public ProfileId $profileId,
        #[EmailNormalizer]
        #[NormalizedName('email', aliases: ['mail'])]
        #[SensitiveData(fallback: new Email('unknown'))]
        public Email $email,
    ) {
    }
}
