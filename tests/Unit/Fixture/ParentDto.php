<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class ParentDto extends ChildDto
{
    public function __construct(
        #[ProfileIdNormalizer]
        public ProfileId $profileId,
        Email $email,
    ) {
        parent::__construct($email);
    }
}
