<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\DataSubjectId;

final class ParentWithPersonalDataDto extends ChildWithPersonalDataDto
{
    public function __construct(
        #[ProfileIdNormalizer]
        #[DataSubjectId]
        public ProfileId $profileId,
        Email $email,
    ) {
        parent::__construct($email);
    }
}
