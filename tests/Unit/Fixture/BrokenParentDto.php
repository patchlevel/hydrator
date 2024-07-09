<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class BrokenParentDto extends ChildDto
{
    public function __construct(
        #[IdNormalizer]
        public ProfileId $profileId,
        private Email $email,
    ) {
        parent::__construct($email);
    }
}
