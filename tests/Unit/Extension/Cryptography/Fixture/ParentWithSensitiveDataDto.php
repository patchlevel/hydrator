<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Fixture;

use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\IdNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;

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
