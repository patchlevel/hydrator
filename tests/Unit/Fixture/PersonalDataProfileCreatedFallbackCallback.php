<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Attribute\PersonalData;

final class PersonalDataProfileCreatedFallbackCallback
{
    public function __construct(
        #[IdNormalizer]
        #[NormalizedName('id')]
        #[DataSubjectId]
        public ProfileId $profileId,
        #[EmailNormalizer]
        #[PersonalData(fallbackCallable: [self::class, 'emailFallback'])]
        public Email $email,
    ) {
    }

    public static function emailFallback(mixed $value, string $subjectId): Email
    {
        return new Email($subjectId . '@example.com');
    }
}
