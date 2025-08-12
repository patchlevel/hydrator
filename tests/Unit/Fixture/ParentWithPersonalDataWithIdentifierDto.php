<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\DataSubjectId;

final class ParentWithPersonalDataWithIdentifierDto extends ChildWithPersonalDataWithIdentifierDto
{
    public function __construct(
        #[IdNormalizer]
        #[DataSubjectId(identifier: 'profile')]
        public ProfileId $profileId,
        Email $email,
    ) {
        parent::__construct($email);
    }
}
