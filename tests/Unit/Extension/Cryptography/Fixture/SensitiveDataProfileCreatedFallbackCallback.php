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

final class SensitiveDataProfileCreatedFallbackCallback
{
    public function __construct(
        #[IdNormalizer]
        #[NormalizedName('id')]
        #[DataSubjectId]
        public ProfileId $profileId,
        #[EmailNormalizer]
        #[SensitiveData(fallbackCallable: [self::class, 'emailFallback'])]
        public Email $email,
    ) {
    }

    public static function emailFallback(string $subjectId): Email
    {
        return new Email($subjectId . '@example.com');
    }
}
