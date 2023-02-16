<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\Ignore;

final class IgnoreParentDto extends ChildDto
{
    public function __construct(
        #[ProfileIdNormalizer]
        public ProfileId $profileId,
        #[Ignore]
        private Email $email,
    ) {
        parent::__construct($email);
    }
}
