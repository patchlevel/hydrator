<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\PersonalData;

abstract class ChildWithPersonalDataWithIdentifierDto
{
    public function __construct(
        #[EmailNormalizer]
        #[PersonalData(identifier: 'profile')]
        private Email $email,
    ) {
    }
}
