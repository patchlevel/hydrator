<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\PersonalData;

abstract class ChildWithPersonalDataDto
{
    public function __construct(
        #[EmailNormalizer]
        #[PersonalData]
        private Email $email,
    ) {
    }
}
