<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\DataSubjectId;

final class ParentWithSensitiveDataDto extends ChildWithSensitiveDataDto
{
    public function __construct(
        #[IdNormalizer]
        #[DataSubjectId]
        public ProfileId $profileId,
        Email $email,
    ) {
        parent::__construct($email);
    }
}
